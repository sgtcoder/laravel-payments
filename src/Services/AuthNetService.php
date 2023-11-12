<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentProfile;

class AuthNetService
{
    public function make_payment($data, $model, $payment = NULL)
    {
        $payment_type = $data['payment_type'] ?? 'one_time';
        $merchant_ref = $data['merchant_ref'];
        $amount = bcmul($data['amount'], 100);
        $cardholder_first_name = $data['firstName'];
        $cardholder_last_name = $data['lastName'];
        $cardholder_name = $cardholder_first_name . ' ' . $cardholder_last_name;
        $last_4 = $data['last_4'];
        $exp_date = str_replace(' ', '', $data['expiryDate']);
        $exp_date_array = explode('/', $exp_date);
        $card_type = $data['cardType'];

        $anet_customer_id = $model->anet_customer_id;

        // Create Customer if Not Exists
        if (!$anet_customer_id) {
            $model->anet()->createCustomerProfile();

            $anet_customer_id = $model->anet()->getCustomerProfileId();

            $model->update([
                'anet_customer_id' => $anet_customer_id,
            ]);
        }

        $payment_profile_response = $model->anet()->createPaymentProfile([
            'dataValue' => $data['opaqueDataValue'],
            'dataDescriptor' => $data['opaqueDataDescriptor'],
            'billingFirstName' => $cardholder_first_name,
            'billingLastName' => $cardholder_last_name,
        ], [
            'last_4' => $last_4,
            'expiry_date' => $exp_date,
            'brand'  => '',
            'type'   => 'card'
        ]);

        $anet_payment_method_id = $payment_profile_response->getCustomerPaymentProfileId();

        if (!$anet_payment_method_id) {
            $error_message = 'Payment Method could not be saved.';

            $payment_data = [
                'status' => false,
                'message' => $error_message,
            ];

            return $payment_data;
        }

        // Save Card
        $payment_profile = PaymentProfile::updateOrCreate(
            [
                'payment_gateway' => 'authnet',
                'model_id' => $model->id,
                'model_type' => get_class($model),
                'payment_method_id' => $anet_payment_method_id,
            ],
            [
                'last_4' => $last_4,
                'exp_date' => $exp_date,
                'card_type' => $card_type,
                'cardholder_name' => $cardholder_name,
            ]
        );

        $payment_profile_id = $payment_profile->id;

        if ($payment_type != 'one_time') {
            $model->update([
                'payment_profile_id' => $payment_profile_id,
            ]);
        }

        $payment_response = $model->anet()->charge($amount, $anet_payment_method_id);
        $transaction_response = $payment_response->getTransactionResponse();

        $payment_data = [
            'model_id' => $model->id,
            'model_type' => get_class($model),
            'model_email' => $model->email,
            'amount' => $data['amount'],
            'merchant_ref' => $data['merchant_ref'],
            'payment_type' => $payment_type,
            'payment_profile_id' => $payment_profile_id,
        ];

        if ($transaction_response->getErrors()) {
            $error_message = $transaction_response->getErrors()[0]->getErrorText();

            $payment_data['status'] = false;
            $payment_data['message'] = $error_message;
        } else if ($transaction_response->getResponseCode()) {
            $transaction_id = $transaction_response->getTransId();

            $payment_data['status'] = true;
            $payment_data['transaction_id'] = $transaction_id;
        }

        $payment = $this->save_payment($payment_data, $payment);

        return $payment_data;
    }

    public function make_payment_token($data, $payment_profile_id, $payment = NULL)
    {
        $payment_profile = PaymentProfile::where('id', $payment_profile_id)->first();
        $model = $payment_profile->model;

        $payment_type = $data['payment_type'] ?? 'recurring';
        $merchant_ref = $data['merchant_ref'];
        $amount = bcmul($data['amount'], 100);
        $cardholder_name = $payment_profile->cardholder_name;
        $exp_date = $payment_profile->exp_date;
        $card_type = $payment_profile->card_type;

        $anet_customer_id = $model->anet_customer_id;
        $anet_payment_method_id = $payment_profile->payment_method_id;

        $payment_response = $model->anet()->charge($amount, $anet_payment_method_id);
        $transaction_response = $payment_response->getTransactionResponse();

        $payment_data = [
            'model_id' => $model->id,
            'model_type' => get_class($model),
            'model_email' => $model->email,
            'amount' => $data['amount'],
            'merchant_ref' => $data['merchant_ref'],
            'payment_type' => $payment_type,
            'payment_profile_id' => $payment_profile_id,
        ];

        if ($transaction_response->getErrors()) {
            $error_message = $transaction_response->getErrors()[0]->getErrorText();

            $payment_data['status'] = false;
            $payment_data['message'] = $error_message;
        } else if ($transaction_response->getResponseCode()) {
            $transaction_id = $transaction_response->getTransId();

            $payment_data['status'] = true;
            $payment_data['transaction_id'] = $transaction_id;
        }

        $payment = $this->save_payment($payment_data, $payment);

        return $payment_data;
    }

    public function update_payment_method($data, $model)
    {
        $cardholder_first_name = $data['firstName'];
        $cardholder_last_name = $data['lastName'];
        $cardholder_name = $cardholder_first_name . ' ' . $cardholder_last_name;
        $last_4 = $data['last_4'];
        $exp_date = str_replace(' ', '', $data['expiryDate']);
        $exp_date_array = explode('/', $exp_date);
        $card_type = $data['cardType'];

        $anet_customer_id = $model->anet_customer_id;

        $old_payment_profile = $model->payment_profile;

        // Create Customer if Not Exists
        if (!$anet_customer_id) {
            $model->anet()->createCustomerProfile();

            $anet_customer_id = $model->anet()->getCustomerProfileId();

            $model->update([
                'anet_customer_id' => $anet_customer_id,
            ]);
        }

        $payment_profile_response = $model->anet()->createPaymentProfile([
            'dataValue' => $data['opaqueDataValue'],
            'dataDescriptor' => $data['opaqueDataDescriptor'],
            'billingFirstName' => $cardholder_first_name,
            'billingLastName' => $cardholder_last_name,
        ], [
            'last_4' => $last_4,
            'expiry_date' => $exp_date,
            'brand'  => '',
            'type'   => 'card'
        ]);

        $anet_payment_method_id = $payment_profile_response->getCustomerPaymentProfileId();

        if (!$anet_payment_method_id) {
            $error_message = 'Payment Method could not be saved.';

            $payment_data = [
                'status' => false,
                'message' => $error_message,
            ];

            return $payment_data;
        }

        // Save Card
        $payment_profile = PaymentProfile::updateOrCreate(
            [
                'payment_gateway' => 'authnet',
                'model_id' => $model->id,
                'model_type' => get_class($model),
                'payment_method_id' => $anet_payment_method_id,
            ],
            [
                'last_4' => $last_4,
                'exp_date' => $exp_date,
                'card_type' => $card_type,
                'cardholder_name' => $cardholder_name,
            ]
        );

        $payment_profile_id = $payment_profile->id;

        $model->update([
            'payment_profile_id' => $payment_profile_id,
        ]);

        // Delete Old Payment Method, if different - Should add authnet call too
        if ($old_payment_profile->payment_method_id != $anet_payment_method_id) {
            $old_payment_profile->delete();
        }

        $payment_data = [];
        if ($anet_payment_method_id) {
            $payment_data['status'] = true;
        } else {
            $payment_data['status'] = false;
        }

        return $payment_data;
    }

    public function make_waived_payment($data, $model, $payment = NULL)
    {
        $data['amount'] = $data['amount'] ?? 0.00;
        $payment_type = $data['payment_type'] ?? 'waived_payment';
        $merchant_ref = $data['merchant_ref'];
        $amount = bcmul($data['amount'], 100);

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
