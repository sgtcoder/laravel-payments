<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

use App\Models\{
    Payment,
    PaymentProfile
};

// https://jacobnarayan.com/blogs/how-to-build-a-custom-e-commerce-integration-with-clover
// https://community.clover.com/questions/61980/what-tokenkeys-do-i-use-for-customer-e-commerce-in.html
// https://community.clover.com/questions/46633/payeezy-to-clover.html
// https://community.clover.com/questions/63323/pakms-api-issue.html

class CloverService
{
    private ?string $url;
    private ?string $token;

    public function __construct($token = null)
    {
        if (app()->environment('production')) {
            $this->url = 'https://scl.clover.com/v1';
        } else {
            $this->url = 'https://scl-sandbox.dev.clover.com/v1';
        }

        $this->token = $token ?? env('CLOVER_PRIVATE_API_KEY');
    }

    public function make_payment($data, $patient = null, $payment = null)
    {
        // https://docs.clover.com/docs/using-the-clover-hosted-iframe
        // https://docs.clover.com/docs/ecommerce-api-tutorials

        $payment_type = $data['payment_type'] ?? 'one-time';
        $merchant_ref = $data['merchant_ref'];
        $amount = bcmul($data['amount'], '100');
        $clover_payment_method_id = request('cloverToken');

        // Make Payment
        $path = '/charges';

        $headers = [
            'Accept' => 'application/json',
            'idempotency-key' => str()->uuid()->toString(),
        ];

        $payload = [
            'amount' => $amount,
            'currency' => 'usd',
            'source' => request('cloverToken'),
            'description' => $merchant_ref,
        ];

        $payment_response = $this->api_call($path, $method_type = 'post', $payload, $headers);

        $payment_status = $payment_response->paid ?? false;
        $transaction_id = $payment_response->id ?? null;
        $transaction_tag = $payment_response->ref_num ?? null;

        if ($payment_response->error ?? null) {
            $bank_message = ucwords(str_replace('_', ' ', $payment_response->error->code ?? null)) . ' ' . ($payment_response->error->message ?? null);
        }

        $payment_data = [
            'amount' => $data['amount'],
            'merchant_ref' => $data['merchant_ref'],
            'payment_type' => $payment_type,
            'patient_id' => $patient->id ?? null,
            'patient_email' => $patient->email ?? null,
        ];

        if ($payment_status) {
            $payment_data['status'] = true;
            $payment_data['transaction_id'] = $transaction_id;
            $payment_data['transaction_tag'] = $transaction_tag;
        } else {
            $payment_data['status'] = false;
            $payment_data['message'] = $bank_message ?? null;
        }

        $payment = $this->save_payment($payment_data, $payment);

        $payment_data['payment'] = $payment;

        return $payment_data;
    }

    public function make_payment_token($data, $payment_profile_id, $payment = null)
    {
        $payment_profile = PaymentProfile::where('id', $payment_profile_id)->first();
        $patient = $payment_profile->patient;
        $clover_customer_id = $patient->clover_customer_id;

        $payment_type = $data['payment_type'] ?? 'recurring';
        $merchant_ref = $data['merchant_ref'];
        $amount = bcmul($data['amount'], '100');

        // Make Payment
        $path = '/charges';

        $headers = [
            'Accept' => 'application/json',
            'idempotency-key' => str()->uuid()->toString(),
        ];

        $payload = [
            'amount' => $amount,
            'currency' => 'usd',
            'source' => $clover_customer_id,
            'description' => $merchant_ref,
        ];

        $payment_response = $this->api_call($path, $method_type = 'post', $payload, $headers);

        $payment_status = $payment_response->paid ?? false;
        $transaction_id = $payment_response->id ?? null;
        $transaction_tag = $payment_response->ref_num ?? null;

        if ($payment_response->error ?? null) {
            $bank_message = ucwords(str_replace('_', ' ', $payment_response->error->code ?? null)) . ' ' . ($payment_response->error->message ?? null);
        }

        $payment_data = [
            'amount' => $data['amount'],
            'merchant_ref' => $data['merchant_ref'],
            'payment_type' => $payment_type,
            'patient_id' => $patient->id,
            'patient_email' => $patient->email,
            'payment_profile_id' => $payment_profile_id,
        ];

        if ($payment_status) {
            $payment_data['status'] = true;
            $payment_data['transaction_id'] = $transaction_id;
            $payment_data['transaction_tag'] = $transaction_tag;
        } else {
            $payment_data['status'] = false;
            $payment_data['message'] = $bank_message ?? null;
        }

        $payment = $this->save_payment($payment_data, $payment);

        return $payment_data;
    }

    public function make_waived_payment($data, $patient, $payment = null)
    {
        $data['amount'] = $data['amount'] ?? 0.00;
        $payment_type = $data['payment_type'] ?? 'waived_payment';
        $payment_reason = $data['payment_reason'] ?? 'Waived Payment';
        $subscription_id = $data['subscription_id'] ?? null;
        $invoice_id = $data['invoice_id'] ?? null;
        $merchant_ref = $data['merchant_ref'] ?? 'Waived Payment';
        $amount = bcmul($data['amount'], '100');

        $payment_data = [
            'amount' => $data['amount'],
            'merchant_ref' => $merchant_ref,
            'payment_type' => $payment_type,
            'payment_reason' => $payment_reason,
            'patient_id' => $patient->id,
            'subscription_id' => $subscription_id,
            'invoice_id' => $invoice_id,
            'patient_email' => $patient->email,
            'status' => true,
            'transaction_id' => null,
        ];

        $payment = $this->save_payment($payment_data, $payment);

        return $payment;
    }

    public function save_payment($data, $payment)
    {
        if ($data['status']) {
            // Success Payment
            if ($payment) {
                $payment->update([
                    'payment_status' => 'success',
                    'transaction_id' => $data['transaction_id'] ?? null,
                    'transaction_tag' => $data['transaction_tag'] ?? null,
                    'payment_profile_id' => $data['payment_profile_id'] ?? null,
                    'payment_type' => $data['payment_type'] ?? null,
                    'payment_reason' => $data['payment_reason'] ?? null,
                ]);
            } else {
                $payment = Payment::create([
                    'email' => $data['patient_email'],
                    'patient_id' => $data['patient_id'],
                    'subscription_id' => $data['subscription_id'] ?? null,
                    'invoice_id' => $data['invoice_id'] ?? null,
                    'amount' => $data['amount'],
                    'payment_status' => 'success',
                    'merchant_ref' => $data['merchant_ref'],
                    'payment_method' => 'manual',
                    'transaction_id' => $data['transaction_id'] ?? null,
                    'transaction_tag' => $data['transaction_tag'] ?? null,
                    'payment_profile_id' => $data['payment_profile_id'] ?? null,
                    'payment_type' => $data['payment_type'] ?? null,
                    'payment_reason' => $data['payment_reason'] ?? null,
                ]);
            }

            $patient = $payment->patient;
            if ($patient->bloodwork_payment ?? null) {
                $patient->update([
                    'new_initial_payment' => true
                ]);
            }
        } else {
            // Failed Payment
            if ($payment) {
                $payment->update([
                    'payment_status' => 'failed',
                    'error_message' => $data['message'],
                    'payment_profile_id' => $data['payment_profile_id'] ?? null,
                    'payment_type' => $data['payment_type'] ?? null,
                    'payment_reason' => $data['payment_reason'] ?? null,
                ]);
            } else {
                $payment = Payment::create([
                    'email' => $data['patient_email'],
                    'patient_id' => $data['patient_id'],
                    'subscription_id' => $data['subscription_id'] ?? null,
                    'invoice_id' => $data['invoice_id'] ?? null,
                    'amount' => $data['amount'],
                    'merchant_ref' => $data['merchant_ref'],
                    'payment_status' => 'failed',
                    'error_message' => $data['message'],
                    'payment_method' => 'manual',
                    'payment_profile_id' => $data['payment_profile_id'] ?? null,
                    'payment_type' => $data['payment_type'] ?? null,
                    'payment_reason' => $data['payment_reason'] ?? null,
                ]);
            }
        }

        return $payment;
    }

    public function create_customer($patient, $clover_payment_token)
    {
        $path = '/customers';

        $headers = [
            'Accept' => 'application/json',
            'idempotency-key' => str()->uuid()->toString(),
        ];

        $payload = [
            'firstName' => $patient->first_name,
            'lastName' => $patient->last_name,
            'email' => $patient->email,
            'phone' => $patient->phone,
            'source' => $clover_payment_token,
        ];

        $data = $this->api_call($path, $method_type = 'post', $payload, $headers);

        $patient->update([
            'clover_customer_id' => $data->id,
        ]);

        $clover_customer_id = $data->id;
        $clover_payment_method_id = $data->sources->data[0];

        $this->save_payment_method($clover_payment_method_id, $patient);

        return $data;
    }

    public function update_card($patient)
    {
        $clover_customer_id = $patient->clover_customer_id;
        $clover_payment_token = request('cloverToken');

        // Create Customer if Not Exists
        if (!$clover_customer_id) {
            $data = $this->create_customer($patient, $clover_payment_token);
        } else {
            // Update Payment Card
            $payment_profile = $patient->payment_profile;
            $clover_payment_method_id = $payment_profile?->payment_method_id;

            // Delete Old Card
            if ($clover_payment_method_id) {
                $path = '/customers/' . $clover_customer_id . '/sources/' . $clover_payment_method_id;

                $headers = [
                    'Accept' => 'application/json',
                    'idempotency-key' => str()->uuid()->toString(),
                ];

                $payload = [];

                $data = $this->api_call($path, $method_type = 'delete', $payload, $headers);

                if ($data->deleted ?? false) {
                    $payment_profile->delete();
                } else {
                    //dd($data);
                }
            }

            // Save New Card
            $path = '/customers/' . $clover_customer_id;

            $headers = [
                'Accept' => 'application/json',
                'idempotency-key' => str()->uuid()->toString(),
            ];

            $payload = [
                'source' => $clover_payment_token,
                'email' => $patient->email
            ];

            $data = $this->api_call($path, $method_type = 'put', $payload, $headers);
        }

        $clover_payment_method_id = $data->sources->data[0] ?? null;

        if ($clover_payment_method_id) {
            $this->save_payment_method($clover_payment_method_id, $patient);
        } else {
            if ($data->error ?? null) {
                $data->message = $data->error->message ?? null;
            }
        }

        $data->clover_payment_method_id = $clover_payment_method_id;

        return $data;
    }

    public function save_payment_method($clover_payment_method_id, $patient)
    {
        // Save Card
        $payment_profile = PaymentProfile::updateOrCreate(
            [
                'payment_gateway' => 'clover',
                'patient_id' => $patient->id,
                'payment_method_id' => $clover_payment_method_id,
            ],
            [
                'last_4' => request('last_4'),
                'exp_date' => request('exp_date'),
                'card_type' => request('card_type'),
                'cardholder_name' => request('cardholder_name'),
            ]
        );

        $patient->update([
            'payment_profile_id' => $payment_profile->id,
        ]);

        return $payment_profile;
    }

    public function api_call($path, $method_type = 'get', $payload = [], $headers = [])
    {
        $headers['Authorization'] = 'Bearer ' . $this->token;

        $url = $this->url . $path;

        $data = Http::timeout(900)->withHeaders($headers)->$method_type($url, $payload)->object();

        return $data;
    }
}
