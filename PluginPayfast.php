<?php
require_once 'modules/admin/models/GatewayPlugin.php';
require_once 'modules/billing/models/class.gateway.plugin.php';

class PluginPayfast extends GatewayPlugin
{
    function getVariables()
    {
        $variables = array (
            lang('Plugin Name') =>  [
                'type' =>  'hidden',
                'description' => lang('How CE sees this plugin ( not to be confused with the Signup Name )'),
                'value' => 'Payfast'
            ],
            lang('Merchant ID') =>  [
                'type' => 'text',
                'description' => lang('Your PayFast Merchant ID'),
                'value' => ''
            ],
            lang('Merchant Key') =>  [
                'type' => 'text',
                'description' => lang('Your PayFast Merchant Key'),
                'value' => ''
            ],
            lang('Passphrase') =>  [
                'type' => 'password',
                'description' => lang('Your PayFast Passphrase'),
                'value' => ''
            ],
            lang('Source IP Security Check?') => [
                'type' => 'yesno',
                'description' => lang('Select to ensure the callback comes from a PayFast IP.'),
                'value' => '1'
            ],
            lang('Test Mode?') => [
                'type' => 'yesno',
                'description' => lang('Select to enable test/sandbox mode'),
                'value' => '0'
            ],
            lang('Invoice After Signup') =>  [
                'type' => 'yesno',
                'description' => lang('Select YES if you want an invoice sent to the client after signup is complete.'),
                'value' => '1'
            ],
            lang('Signup Name') =>  [
                'type' => 'text',
                'description' => lang('Select the name to display in the signup process for this payment type. Example: eCheck or Credit Card.'),
                'value' => 'PayFast'
            ]
        );
        return $variables;
    }

    function credit($params)
    {
    }

    function singlePayment($params)
    {

        if ($this->getVariable('Test Mode?') == '1') {
            $payFastURL = 'https://sandbox.payfast.co.za/eng/process';
        } else {
            $payFastURL = 'https://www.payfast.co.za/eng/process';
        }

        $callBackURL = $params['clientExecURL'] . '/plugins/gateways/payfast/callback.php';
        if ($params['isSignup'] == 1) {
            if ($this->settings->get('Signup Completion URL') != '') {
                $returnURL = $this->settings->get('Signup Completion URL'). '?success=1';
                $returnURLCancel = $this->settings->get('Signup Completion URL');
            } else {
                $returnURL = $params['clientExecURL'] . '/order.php?step=complete&pass=1';
                $returnURLCancel = $params['clientExecURL'] . '/order.php?step=3';
            }
        } else {
            $returnURL = $params['invoiceviewURLSuccess'];
            $returnURLCancel = $params['invoiceviewURLCancel'];
        }

        $data = [
            'merchant_id' => $this->getVariable('Merchant ID'),
            'merchant_key' => $this->getVariable('Merchant Key'),
            'return_url' => $returnURL,
            'cancel_url' => $returnURLCancel,
            'notify_url' => $callBackURL,
            'name_first' => $params["userFirstName"],
            'name_last' => $params["userLastName"],
            'email_address' => $params["userEmail"],

            // Item details
            'm_payment_id' => $params['invoiceNumber'],
            'amount' => sprintf("%01.2f", round($params['invoiceTotal'], 2)),
            'item_name' => 'Invoice #' . $params['invoiceNumber'],
        ];

        $pfOutput = '';
        foreach ($data as $key => $val) {
            if (!empty($val)) {
                $pfOutput .= $key .'='. urlencode(trim($val)) .'&';
            }
        }
        $getString = substr($pfOutput, 0, -1);
        $passPhrase = $this->getVariable('Passphrase');
        if (isset($passPhrase) && $passPhrase != '') {
            $getString .= '&passphrase=' . urlencode(trim($passPhrase));
        }
        $data['signature'] = md5($getString);

        $htmlForm = '<form action="' . $payFastURL. '" method="post" name="frmPayFast">';
        foreach ($data as $name => $value) {
            $htmlForm .= '<input name="'.$name.'" type="hidden" value="'.$value.'" />' . "\n";
        }

        $htmlForm .= "<script language=\"JavaScript\">\n";
        $htmlForm .= "document.forms['frmPayFast'].submit();\n";
        $htmlForm .= "</script>\n";
        $htmlForm .= "</form>\n";

        echo $htmlForm;
        die();
    }

    function autopayment($params)
    {
    }
}
