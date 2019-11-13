<?php
require_once 'modules/admin/models/PluginCallback.php';
require_once 'modules/admin/models/StatusAliasGateway.php';
require_once 'modules/billing/models/class.gateway.plugin.php';
require_once 'modules/billing/models/Invoice_EventLog.php';
require_once 'modules/admin/models/Error_EventLog.php';

class PluginPayfastCallback extends PluginCallback
{
    function processCallback()
    {

        if ($this->settings->get('plugin_payfast_Test Mode?')) {
            $pfHost = 'sandbox.payfast.co.za';
        } else {
            $pfHost = 'www.payfast.co.za';
        }

        $pfData = $_POST;

        foreach ($pfData as $key => $val) {
            $pfData[$key] = stripslashes($val);
        }

        foreach ($pfData as $key => $val) {
            if ($key != 'signature') {
                $pfParamString .= $key .'='. urlencode($val) .'&';
            }
        }
        // Remove the last '&' from the parameter string
        $pfParamString = substr($pfParamString, 0, -1);
        $pfTempParamString = $pfParamString;

        $passPhrase = $this->settings->get('plugin_payfast_Passphrase');

        if (!empty($passPhrase)) {
            $pfTempParamString .= '&passphrase='.urlencode($passPhrase);
        }
        $signature = md5($pfTempParamString);

        if ($signature != $pfData['signature']) {
            CE_Lib::log(4, 'Invalid Signature');
            die('Invalid Signature');
        }

        $url = 'https://'. $pfHost .'/eng/query/validate';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $caPathOrFile = \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath();
        if (is_dir($caPathOrFile)) {
            curl_setopt($ch, CURLOPT_CAPATH, $caPathOrFile);
        } else {
            curl_setopt($ch, CURLOPT_CAINFO, $caPathOrFile);
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $pfParamString);
        $response = curl_exec($ch);
        curl_close($ch);

        $lines = explode("\r\n", $response);
        $verifyResult = trim($lines[0]);

        if (strcasecmp($verifyResult, 'VALID') != 0) {
            CE_Lib::log(4, 'Data not valid');
            die('Data not valid');
        }

        if ($pfData ['payment_status'] == 'COMPLETE') {
            $invoiceId = $pfData['m_payment_id'];
            $cPlugin = new Plugin($invoiceId, "payfast", $this->user);
            $cPlugin->setTransactionID($pfData['pf_payment_id']);
            $cPlugin->setAmount($pfData['amount_gross']);
            $cPlugin->setAction('charge');
            $cPlugin->PaymentAccepted($pfData['amount_gross'], "PayFast payment was accepted.", $pfData['pf_payment_id']);
        }
    }
}
