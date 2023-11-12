<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentProfile;
use Omnipay\Common\CreditCard;

class ElavonService
{
    public function make_payment($data, $model, $payment = NULL)
    {
        $payment_type = $data['payment_type'] ?? 'one_time';
        $merchant_ref = $data['merchant_ref'];
        $amount = $data['amount'];
        $cardholder_first_name = $data['firstName'];
        $cardholder_last_name = $data['lastName'];
        $cardholder_name = $cardholder_first_name . ' ' . $cardholder_last_name;
        $card_number = str_replace(' ', '', $data['card_number']);
        $exp_date = str_replace(' ', '', $data['exp_date']);
        $exp_array = explode('/', $exp_date);
        $exp_month = $exp_array[0];
        $exp_year = $exp_array[1];
        $cvv = $data['cvv'];
        $card_type = $data['card_type'];

        $billingAddress1 = $data['billingAddress1'];
        $billingCity = $data['billingCity'];
        $billingState = $data['billingState'];
        $billingPostcode = $data['billingPostcode'];

        $gateway = \Omnipay\Omnipay::create('Elavon_Converge')->initialize([
            'merchantId' => config('elavon.merchant_id'),
            'username' => config('elavon.username'),
            'password' => config('elavon.password'),
            'testMode' => !app()->environment('production'),
        ]);

        $card = new CreditCard([
            'firstName' => $cardholder_first_name,
            'lastName' => $cardholder_last_name,
            'number' => $card_number,
            'expiryMonth' => $exp_month,
            'expiryYear' => $exp_year,
            'cvv' => $cvv,
            'billingAddress1' => $billingAddress1,
            'billingCity' => $billingCity,
            'billingState' => $billingState,
            'billingPostcode' => $billingPostcode,
        ]);

        $params = array(
            'amount' => $amount,
            'card' => $card,
            'ssl_invoice_number' => 1,
            'ssl_show_form' => 'false',
            'ssl_result_format' => 'ASCII',
        );

        $payment_data = [
            'model_id' => $model->id,
            'model_type' => get_class($model),
            'model_email' => $model->email,
            'amount' => $data['amount'],
            'merchant_ref' => $data['merchant_ref'],
            'payment_type' => $payment_type,
        ];

        try {
            $response = $gateway->purchase($params)->send();

            if ($response->isSuccessful()) {
                // successful
                $transaction_id = $response->getTransactionReference();

                $payment_data['status'] = true;
                $payment_data['transaction_id'] = $transaction_id;
            } else {
                $error_message = $response->getMessage();

                $payment_data['status'] = false;
                $payment_data['message'] = $error_message;
            }
        } catch (\Exception $ex) {
            $error_message = $ex->getMessage();

            $payment_data['status'] = false;
            $payment_data['message'] = $error_message;
        }

        $payment = $this->save_payment($payment_data, $payment);

        return $payment_data;
    }

    public function make_waived_payment($data, $model, $payment = NULL)
    {
        $data['amount'] = $data['amount'] ?? 0.00;
        $payment_type = $data['payment_type'] ?? 'waived_payment';
        $merchant_ref = $data['merchant_ref'];
        $amount = bcmul($data['amount'], '100');

        $payment_data = [
            'model_id' => $model->id,
            'model_type' => get_class($model),
            'model_email' => $model->email,
            'amount' => $data['amount'],
            'merchant_ref' => $data['merchant_ref'],
            'payment_type' => $payment_type,
            'status' => true,
            'transaction_id' => NULL,
        ];

        $payment = $this->save_payment($payment_data, $payment);

        return $payment_data;
    }

    public function save_payment($data, $payment)
    {
        if ($data['status']) {
            // Success Payment
            if ($payment) {
                $payment->update([
                    'payment_status' => 'success',
                    'transaction_id' => $data['transaction_id'],
                    'payment_profile_id' => $data['payment_profile_id'] ?? NULL,
                    'payment_type' => $data['payment_type'] ?? NULL,
                ]);
            } else {
                $payment = Payment::create([
                    'email' => $data['model_email'],
                    'model_id' => $data['model_id'],
                    'model_type' => $data['model_type'],
                    'amount' => $data['amount'],
                    'payment_status' => 'success',
                    'merchant_ref' => $data['merchant_ref'],
                    'payment_method' => 'manual',
                    'transaction_id' => $data['transaction_id'],
                    'payment_profile_id' => $data['payment_profile_id'] ?? NULL,
                    'payment_type' => $data['payment_type'] ?? NULL,
                ]);
            }
        } else {
            // Failed Payment
            if ($payment) {
                $payment->update([
                    'payment_status' => 'failed',
                    'error_message' => $data['message'],
                    'payment_profile_id' => $data['payment_profile_id'] ?? NULL,
                    'payment_type' => $data['payment_type'] ?? NULL,
                ]);
            } else {
                $payment = Payment::create([
                    'email' => $data['model_email'],
                    'model_id' => $data['model_id'],
                    'model_type' => $data['model_type'],
                    'amount' => $data['amount'],
                    'merchant_ref' => $data['merchant_ref'],
                    'payment_status' => 'failed',
                    'error_message' => $data['message'],
                    'payment_method' => 'manual',
                    'payment_profile_id' => $data['payment_profile_id'] ?? NULL,
                    'payment_type' => $data['payment_type'] ?? NULL,
                ]);
            }
        }

        return $payment;
    }

    public function get_currency($country)
    {
        $currency = 'USD';

        switch ($country) {
            case 'US':
                $currency = 'USD';
                break;
            case 'CA':
                $currency = 'CAD';
                break;
        }

        return $currency;
    }
}
