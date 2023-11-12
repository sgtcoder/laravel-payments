<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentProfile;

use Payeezy_Client;
use Payeezy_CreditCard;
use Payeezy_Token;

class PayeezyService
{
    public function make_payment($data)
    {
        $merchant_ref = $data['merchant_ref'];
        $amount = bcmul($data['amount'], '100');
        $cardholder_name = $data['cardholder_name'];
        $card_number = str_replace(' ', '', $data['card_number']);
        $exp_date = explode('/', str_replace(' ', '', $data['exp_date']));
        $exp_date = $exp_date[0] . substr($exp_date[1], 2, 2);
        $cvv = $data['cvv'];
        $card_type = $this->fix_card_type($data['card_type']);

        $apiKey = config('payeezy.api_key');
        $apiSecret = config('payeezy.api_secret');
        $token = config('payeezy.merchant_token');

        if (app()->environment('production')) {
            $url = "https://api.payeezy.com/v1/transactions";
        } else {
            $url = "https://api-cert.payeezy.com/v1/transactions";
        }

        $client = new Payeezy_Client();
        $client->setApiKey($apiKey);
        $client->setApiSecret($apiSecret);
        $client->setMerchantToken($token);
        $client->setUrl($url);

        $card_transaction = new Payeezy_CreditCard($client);

        $transaction_response = $card_transaction->purchase([
            'merchant_ref' => $merchant_ref,
            'amount' => $amount,
            'currency_code' => 'USD',
            'credit_card' => [
                'type' => $card_type,
                'cardholder_name' => $cardholder_name,
                'card_number' => $card_number,
                'exp_date' => $exp_date,
                'cvv' => $cvv,
            ]
        ]);

        $transaction_id = $transaction_response->transaction_id ?? NULL;
        $transaction_tag = $transaction_response->transaction_tag ?? NULL;
        $bank_message = $transaction_response->bank_message ?? NULL;

        if ($bank_message == 'Approved') {
            $payment_status = true;
        } else {
            $payment_status = false;
        }

        return [
            'status' => $payment_status,
            'message' => $bank_message ?? $transaction_response->Error->messages[0]->description ?? NULL,
            'data' => [
                'transaction_id' => $transaction_id,
                'transaction_tag' => $transaction_tag,
                'card' => $transaction_response->card ?? NULL,
                'card_token' => ($transaction_response->token->token_data->value ?? NULL),
            ],
        ];
    }

    public function make_payment_token($data, $payment_profile_id)
    {
        $payment_profile = PaymentProfile::where('id', $payment_profile_id)->first();

        $merchant_ref = $data['merchant_ref'];
        $amount = bcmul($data['amount'], '100');
        $cardholder_name = $payment_profile->cardholder_name;
        $exp_date = $payment_profile->exp_date;
        $card_type = $this->fix_card_type($payment_profile->card_type);

        $apiKey = config('payeezy.api_key');
        $apiSecret = config('payeezy.api_secret');
        $token = config('payeezy.merchant_token');

        if (app()->environment('production')) {
            $url = "https://api.payeezy.com/v1/transactions";
        } else {
            $url = "https://api-cert.payeezy.com/v1/transactions";
        }

        $client = new Payeezy_Client();
        $client->setApiKey($apiKey);
        $client->setApiSecret($apiSecret);
        $client->setMerchantToken($token);
        $client->setUrl($url);

        $card_transaction = new Payeezy_Token($client);

        $transaction_response = $card_transaction->purchase([
            'merchant_ref' => $merchant_ref,
            'amount' => $amount,
            'currency_code' => 'USD',
            'token' => [
                'token_type' => 'FDToken',
                'token_data' => [
                    'type' => $card_type,
                    'value' => $payment_profile->payment_profile_id,
                    'cardholder_name' => $cardholder_name,
                    'exp_date' => $exp_date,
                ]
            ]
        ]);

        $transaction_id = $transaction_response->transaction_id ?? NULL;
        $transaction_tag = $transaction_response->transaction_tag ?? NULL;
        $bank_message = $transaction_response->bank_message ?? NULL;

        if ($bank_message == 'Approved') {
            $payment_status = true;
        } else {
            $payment_status = false;
        }

        return [
            'status' => $payment_status,
            'message' => $bank_message ?? $transaction_response->Error->messages[0]->description ?? NULL,
            'data' => [
                'transaction_id' => $transaction_id,
                'transaction_tag' => $transaction_tag,
                'card' => $transaction_response->card ?? NULL,
                'card_token' => ($transaction_response->token->token_data->value ?? NULL),
            ],
        ];
    }

    public function tokenize_card($data)
    {
        $merchant_ref = 'Tokenize-Card';
        $amount = bcmul('1.00', '100');
        $cardholder_name = $data['cardholder_name'];
        $card_number = str_replace(' ', '', $data['card_number']);
        $exp_date = explode('/', str_replace(' ', '', $data['exp_date']));
        $exp_date = $exp_date[0] . substr($exp_date[1], 2, 2);
        $cvv = $data['cvv'];
        $card_type = $this->fix_card_type($data['card_type']);

        $apiKey = config('payeezy.api_key');
        $apiSecret = config('payeezy.api_secret');
        $token = config('payeezy.merchant_token');

        if (app()->environment('production')) {
            $url = "https://api.payeezy.com/v1/transactions";
        } else {
            $url = "https://api-cert.payeezy.com/v1/transactions";
        }

        $client = new Payeezy_Client();
        $client->setApiKey($apiKey);
        $client->setApiSecret($apiSecret);
        $client->setMerchantToken($token);
        $client->setUrl($url);

        $card_transaction = new Payeezy_CreditCard($client);

        $transaction_response = $card_transaction->authorize([
            'merchant_ref' => $merchant_ref,
            'amount' => $amount,
            'currency_code' => 'USD',
            'credit_card' => [
                'type' => $card_type,
                'cardholder_name' => $cardholder_name,
                'card_number' => $card_number,
                'exp_date' => $exp_date,
                'cvv' => $cvv,
            ]
        ]);

        $transaction_id = $transaction_response->transaction_id ?? NULL;
        $transaction_tag = $transaction_response->transaction_tag ?? NULL;
        $bank_message = $transaction_response->bank_message ?? NULL;

        if ($bank_message == 'Approved') {
            $payment_status = true;

            // Void
            $void_transaction_response = $card_transaction->void($transaction_id, [
                'transaction_tag' => $transaction_tag,
                'amount' => $amount,
                'currency_code' => 'USD',
            ]);
        } else {
            $payment_status = false;
        }

        return [
            'status' => $payment_status,
            'message' => $bank_message ?? $transaction_response->Error->messages[0]->description ?? NULL,
            'data' => [
                'transaction_id' => $transaction_id,
                'transaction_tag' => $transaction_tag,
                'card' => $transaction_response->card ?? NULL,
                'card_token' => ($transaction_response->token->token_data->value ?? NULL),
            ],
        ];
    }

    public function make_waived_payment($data, $model, $payment = NULL)
    {
        $data['amount'] = $data['amount'] ?? 0.00;
        $payment_type = $data['payment_type'] ?? 'waived_payment';
        $payment_reason = $data['payment_reason'] ?? 'Waived Payment';
        $subscription_id = $data['subscription_id'] ?? NULL;
        $invoice_id = $data['invoice_id'] ?? NULL;
        $merchant_ref = $data['merchant_ref'] ?? 'Waived Payment';
        $amount = bcmul($data['amount'], '100');

        $payment_data = [
            'model_id' => $model->id,
            'model_type' => get_class($model),
            'model_email' => $model->email,
            'amount' => $data['amount'],
            'merchant_ref' => $merchant_ref,
            'payment_type' => $payment_type,
            'payment_reason' => $payment_reason,
            'subscription_id' => $subscription_id,
            'invoice_id' => $invoice_id,
            'status' => true,
            'transaction_id' => NULL,
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

    public function fix_card_type($card_type)
    {
        // Conversions
        switch ($card_type) {
            case 'amex':
                $card_type = 'American Express';
                break;
            case 'dinersclub':
                $card_type = 'Diners Club';
                break;
            case 'unionpay':
                $card_type = 'China Union Pay';
                break;
            default:
                $card_type = ucwords($card_type);
                break;
        }

        return $card_type;
    }
}
