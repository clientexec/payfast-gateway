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

        // IP Based Security Check
        // This is a setting that a user can disable (enabled by default), because it could break integration if the user is using CloudFlare without mod_cloudflare.
        if ($this->settings->get('plugin_payfast_Source IP Security Check?') == 1) {
            $validHosts = [
                'www.payfast.co.za',
                'sandbox.payfast.co.za',
                'w1w.payfast.co.za',
                'w2w.payfast.co.za',
            ];
            $validIps = [];
            foreach ($validHosts as $pfHostname) {
                $ips = gethostbynamel($pfHostname);
                if ($ips !== false) {
                    $validIps = array_merge($validIps, $ips);
                }
            }
            $validIps = array_unique($validIps);

            if (!in_array(CE_Lib::getRemoteAddr(), $validIps)) {
                CE_Lib::log(4, 'Valid IPs:');
                CE_Lib::log(4, $validIps);
                CE_Lib::log(4, 'Invalid IP: ' . CE_Lib::getRemoteAddr());
                die('Source IP not Valid');
            }
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
