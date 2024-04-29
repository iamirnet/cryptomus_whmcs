<?php

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require 'cryptomusgateway/vendor/autoload.php';

function cryptomusgateway_MetaData(): array
{
    return [
        'DisplayName' => 'Cryptomus',
        'APIVersion' => '1.1',
        'DisableLocalCreditCardInput' => true,
        'failedEmail' => 'Payment with Cryptomus Failed',
        'successEmail' => 'Payment with Cryptomus confirmed',
        'pendingEmail' => 'Payment with Cryptomus is waiting',
        'TokenisedStorage' => false,
    ];
}

function cryptomusgateway_config(): array
{
    return [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'Cryptomus',
        ],
        'apiKey' => [
            'FriendlyName' => 'API key',
            'Type' => 'text',
            'Description' => 'Enter your API key here',
        ],
        'merchantUuid' => [
            'FriendlyName' => 'Merchant ID',
            'Type' => 'text',
            'Description' => 'Enter your Merchant ID here',
        ],
        'subtract' => [
            'FriendlyName' => 'Subtract',
            'Type' => 'dropdown',
            'Options' => range(0, 100),
            'Description' => 'Percentage of the acceptance fee charged to the client',
        ],
        'comissionMode' => [
            'FriendlyName' => 'Comission',
            'Type' => 'yesno',
            'Description' => 'Take into account commission on the client side?',
        ],
        'payNowLabel' => [
            'FriendlyName' => 'Pay Now Button Text',
            'Type' => 'text',
            'Default' => 'Pay with Cryptomus',
            'Description' => 'Enter the text for the payment button',
        ],
    ];
}

/**
 * @throws \Cryptomus\Api\RequestBuilderException
 */
function cryptomusgateway_link(array $params): string
{
    $apiKey = $params['apiKey'];
    $merchantUuid = $params['merchantUuid'];
    $langPayNow = $params['payNowLabel'] ?? 'Pay with Cryptomus';

    $invoiceId = $params['invoiceid'];
    $amount = $params['amount'];
    $currencyCode = $params['currency'];

    $systemUrl = $params['systemurl'];
    $returnUrl = $params['returnurl'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion'];

    $data = [];
    $data['amount'] = (string)$amount;
    $data['currency'] = $currencyCode;
    $data['order_id'] = 'whmcs_id_' . $invoiceId;
    $data['url_return'] = $returnUrl;
    $data['url_callback'] = $systemUrl . '/modules/gateways/callback/' . $moduleName . '.php';
    $data['is_payment_multiple'] = true;
    $data['lifetime'] = '43200';
    $data['is_refresh'] = false;
    $data['whmcs_version'] = $whmcsVersion;

    if ($_SERVER['SCRIPT_NAME'] === '/viewinvoice.php') {
       $data['is_refresh'] = true; 
    }

    if (isset($params['subtract'])) {
        $data['subtract'] = $params['subtract'];
    }

    $payment = \Cryptomus\Api\Client::payment($apiKey, $merchantUuid);

    try {
        $paymentCreate = $payment->create($data);
    } catch (\Exception $e) {
        // Handle exception or error logging
        return 'Error processing payment: ' . $e->getMessage();
    }
 
    $htmlOutput = '<form action="' . htmlspecialchars($paymentCreate['url'], ENT_QUOTES) . '">';
    $htmlOutput .= '<input type="submit" value="' . htmlspecialchars($langPayNow, ENT_QUOTES) . '"/>';
    $htmlOutput .= '</form>';

    return $htmlOutput;
}
