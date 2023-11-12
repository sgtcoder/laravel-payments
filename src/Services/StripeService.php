<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentProfile;
use Stripe\StripeClient;
use Stripe\Exception\CardException;

class StripeService
{
    public function make_payment($data, $model, $payment = NULL)
    {
        $payment_type = $data['payment_type'] ?? 'one_time';
        $merchant_ref = $data['merchant_ref'];
        $amount = bcmul($data['amount'], '100');
        $cardholder_first_name = $data['firstName'];
        $cardholder_last_name = $data['lastName'];
        $cardholder_name = $cardholder_first_name . ' ' . $cardholder_last_name;
        $card_number = str_replace(' ', '', $data['card_number']);
        $exp_date = str_replace(' ', '', $data['exp_date']);
        $exp_array = explode('/', $exp_date);
        $exp_month = str()->padLeft($exp_array[0], 2, '0') ?? NULL;
        $exp_year = $exp_array[1] ?? NULL;
        $cvv = $data['cvv'];
        $card_type = $data['card_type'];
        $last_4 = $data['last_4'];
        $currency = $this->get_currency($data['country']);
        $save_payment_method = $data['save_payment_method'];
        $saved_payment_method_id = $data['saved_payment_method_id'];
        $stripe_payment_method_id = $data['payment_method_id'];

        if ($saved_payment_method_id) {
            $stripe_payment_method_id = $model->payment_profiles->where('payment_gateway', 'stripe')->where('id', $saved_payment_method_id)->first()?->payment_method_id;

            $payment_profile_id = $saved_payment_method_id;
        }

        if (!$stripe_payment_method_id) {
            $payment_data = [
                'status' => false,
                'message' => 'Payment method could not be found',
            ];

            return $payment_data;
        }

        $stripe = new StripeClient(config('stripe.secret_key'));

        $stripe_customer_id = $model->stripe_customer_id;

        // Create Customer if Not Exists
        if (!$stripe_customer_id) {
            $stripe_customer = $stripe->customers->create([
                'description' => $model->name,
                'email' => $model->email,
            ]);

            $stripe_customer_id = $stripe_customer->id;

            $model->update([
                'stripe_customer_id' => $stripe_customer_id,
            ]);
        }

        // Attach Payment Method
        if ($save_payment_method && !$saved_payment_method_id) {
            $stripe->paymentMethods->attach(
                $stripe_payment_method_id,
                ['customer' => $stripe_customer_id]
            );

            // Save Card
            $payment_profile = PaymentProfile::create([
                'payment_gateway' => 'stripe',
                'model_id' => $model->id,
                'model_type' => get_class($model),
                'payment_method_id' => $stripe_payment_method_id,
                'last_4' => $last_4,
                'exp_date' => $exp_date,
                'card_type' => $card_type,
                'cardholder_name' => $cardholder_name,
            ]);

            $payment_profile_id = $payment_profile->id;

            if ($payment_type != 'one_time') {
                $model->update([
                    'payment_profile_id' => $payment_profile_id,
                ]);
            }
        }

        // Make Payment Intent
        $payment_intent = $stripe->paymentIntents->create([
            'customer' => $stripe_customer_id,
            'payment_method' => $stripe_payment_method_id,
            'amount' => $amount,
            'currency' => $currency,
            'automatic_payment_methods' => ['enabled' => true, 'allow_redirects' => 'never'],
        ]);

        // Charge Payment Intent
        $payment_response = $stripe->paymentIntents->confirm(
            $payment_intent->id,
            ['payment_method' => $stripe_payment_method_id]
        );

        $payment_data = [
            'amount' => $data['amount'],
            'merchant_ref' => $data['merchant_ref'],
            'model_id' => $model->id,
            'model_type' => get_class($model),
            'model_email' => $model->email,
            'payment_profile_id' => $payment_profile_id ?? NULL,
        ];

        if ($payment_response->status == 'succeeded') {
            $transaction_id = $payment_response->id;

            $payment_data['status'] = true;
            $payment_data['transaction_id'] = $transaction_id;
        } else {
            $error_message = $payment_response->last_payment_error;

            $payment_data['status'] = false;
            $payment_data['message'] = $error_message;
        }

        $payment = $this->save_payment($payment_data, $payment);

        return $payment_data;
    }

    public function delete_payment_profile(PaymentProfile $payment_profile)
    {
        $stripe = new StripeClient(config('stripe.secret_key'));

        $model = $payment_profile->model;
        $stripe_customer_id = $model->stripe_customer_id;

        // Delete Old Payment Method
        $stripe->paymentMethods->detach(
            $payment_profile->payment_method_id,
        );

        $payment_profile->delete();

        return true;
    }

    public function make_payment_token($data, $payment_profile_id, $payment = NULL)
    {
        $payment_profile = PaymentProfile::where('id', $payment_profile_id)->first();
        $model = $payment_profile->model;

        $merchant_ref = $data['merchant_ref'];
        $amount = bcmul($data['amount'], '100');
        $cardholder_name = $payment_profile->cardholder_name;
        $exp_date = $payment_profile->exp_date;
        $card_type = $payment_profile->card_type;

        $stripe = new StripeClient(config('stripe.secret_key'));

        $stripe_customer_id = $model->stripe_customer_id;
        $stripe_payment_method_id = $payment_profile->payment_method_id;

        // Make Payment Intent
        $payment_intent = $stripe->paymentIntents->create([
            'customer' => $stripe_customer_id,
            'payment_method' => $stripe_payment_method_id,
            'amount' => $amount,
            'currency' => 'USD',
        ]);

        // Charge Payment Intent
        $payment_response = $stripe->paymentIntents->confirm(
            $payment_intent->id,
            ['payment_method' => $stripe_payment_method_id]
        );

        $payment_data = [
            'amount' => $data['amount'],
            'merchant_ref' => $data['merchant_ref'],
            'model_id' => $model->id,
            'model_type' => 'App\\Models\\' . $model->getShortName(),
            'model_email' => $model->email,
            'payment_profile_id' => $payment_profile_id,
        ];

        if ($payment_response->status == 'succeeded') {
            $transaction_id = $payment_response->id;

            $payment_data['status'] = true;
            $payment_data['transaction_id'] = $transaction_id;
        } else {
            $error_message = $payment_response->last_payment_error;

            $payment_data['status'] = false;
            $payment_data['message'] = $error_message;
        }

        $payment = $this->save_payment($payment_data, $payment);

        return $payment_data;
    }

    public function update_payment_method($data, $model)
    {
        $cardholder_first_name = $data['firstName'];
        $cardholder_last_name = $data['lastName'];
        $cardholder_name = $cardholder_first_name . ' ' . $cardholder_last_name;
        $card_number = str_replace(' ', '', $data['cardNumber']);
        $last_4 = substr($data['cardNumber'], -4);
        $exp_date = str_replace(' ', '', $data['expiryDate']);
        $exp_date_array = explode('/', $exp_date);
        $cvv = $data['cardCode'];
        $card_type = $data['card_type'];

        $stripe = new StripeClient(config('stripe.secret_key'));
        $stripe_customer_id = $model->stripe_customer_id;

        $old_payment_profile = $model->payment_profile;

        // Create Customer if Not Exists
        if (!$stripe_customer_id) {
            $stripe_customer = $stripe->customers->create([
                'description' => $model->name,
                'email' => $model->email,
            ]);

            $stripe_customer_id = $stripe_customer->id;

            $model->update([
                'stripe_customer_id' => $stripe_customer_id,
            ]);
        }

        try {
            // Create Payment Method
            $stripe_payment_method = $stripe->paymentMethods->create([
                'type' => 'card',
                'card' => [
                    'number' => $card_number,
                    'exp_month' => $exp_date_array[0],
                    'exp_year' => $exp_date_array[1],
                    'cvc' => $cvv,
                ],
            ]);
        } catch (CardException $er) {
            return ['status' => false, 'message' => $er->getMessage()];
        }

        $stripe_payment_method_id = $stripe_payment_method->id;

        // Attach Payment Method
        $stripe->paymentMethods->attach(
            $stripe_payment_method_id,
            ['customer' => $stripe_customer_id]
        );

        // Save Card
        $payment_profile = PaymentProfile::create([
            'payment_gateway' => 'stripe',
            'model_id' => $model->id,
            'model_type' => 'App\\Models\\' . $model->getShortName(),
            'payment_method_id' => $stripe_payment_method_id,
            'last_4' => $last_4,
            'exp_date' => $exp_date,
            'card_type' => $card_type,
            'cardholder_name' => $cardholder_name,
        ]);

        $payment_profile_id = $payment_profile->id;

        $model->update([
            'payment_profile_id' => $payment_profile_id,
        ]);

        // Delete Old Payment Method
        $stripe->paymentMethods->detach(
            $old_payment_profile->payment_method_id,
        );

        $old_payment_profile->delete();

        $payment_data = [];
        if ($payment_profile_id) {
            $payment_data['status'] = true;
        } else {
            $payment_data['status'] = false;
        }

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
