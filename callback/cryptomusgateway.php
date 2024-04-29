<?php
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
require_once __DIR__ . '/../../../includes/functions.php';


// Detect module name from filename.
$gatewayModuleName = basename(__FILE__, '.php');

require __DIR__ . '/../' . $gatewayModuleName . '/vendor/autoload.php';

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
    http_response_code(403);
    die('Module Not Activated');
}


// Retrieve data returned in payment gateway callback
$sign = $data['sign'] ?? '';
unset($data['sign']);

$success = !empty($data['is_final']) && ($data['status'] === 'paid' || $data['status'] === 'paid_over');
$transactionStatus = $success ? 'Success' : 'Failure';

if ($sign !== md5(base64_encode(json_encode($data)) . $gatewayParams['apiKey'])) {
    http_response_code(403);
    die('Hash Verification Failure');
}

$invoiceId = preg_replace('/^whmcs_id_/', '', $data['order_id'] ?? '');
$transactionId = $data['txid'] ?? '';
$invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);
$comission = $gatewayParams['comissionMode'];

if ($success && $transactionId) {
    checkCbTransID($transactionId);
    logTransaction($gatewayParams['name'], $data, $transactionStatus);
}

if ($success) {
    $paymentAmount = $data['amount'];
    if ($data['status'] === 'paid_over' && isset($data['currency'])) {
        if (mb_strtoupper($data['currency']) === 'USD' && isset($data['payment_amount_usd'])) {
            $paymentAmount = $data['payment_amount_usd'];
        } else {
            if ($comission === 'on') {
                $paymentAmount = convert($data['payer_currency'], $data['currency'], $data['payment_amount']);
            } else {
                $paymentAmount = convert($data['payer_currency'], $data['currency'], $data['merchant_amount']);
            }
        }
    }

    addInvoicePayment(
        $invoiceId,
        $transactionId,
        $paymentAmount,
        '0.00',
        $gatewayModuleName
    );

    $rez = sendMessage("Payment with Cryptomus confirmed", (int) $invoiceId, ['tx_id' => $transactionId]);
}

function convert($from, $to, $amount)
{
    if (mb_strtoupper($from) === mb_strtoupper($to)) {
        return $amount;
    }

    $result = file_get_contents("https://api.cryptomus.com/v1/exchange-rate/$from/list?to=$to");
    $result = json_decode($result, true);

    if (empty($result['result'])) {
        return null;
    }

    foreach ($result['result'] as $item) {
        if ($item['to'] === mb_strtoupper($to)) {
            return bcmul($item['course'], $amount, 2);
        }
    }

    return null;
}

die('OK');
