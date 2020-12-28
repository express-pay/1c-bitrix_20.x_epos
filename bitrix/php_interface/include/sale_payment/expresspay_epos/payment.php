<?
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule("sale");
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

log_info('payment','Begin payment process');

$uri = $_SERVER['REQUEST_URI'];
$uri = explode("/", $uri);
$isSet = array_search('make', $uri);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $isSet != false)
{
	if(isset($_REQUEST['result']))
	{
		if($_REQUEST['result'] == 'success' && validSignature($_REQUEST['Signature']))
		{
			$inv_id = $_REQUEST['ExpressPayAccountNumber'];
			$out_summ = $_REQUEST['ExpressPayAmount'];

			
				$token = CSalePaySystemAction::GetParamValue("EPOS_TOKEN");
				$secret_word = CSalePaySystemAction::GetParamValue("EPOS_SECRET_WORD");
				$isTest = CSalePaySystemAction::GetParamValue("EPOS_IS_TEST_API");

				$url;

				if ($isTest == "Y")
				{
					$url = 'https://sandbox-api.express-pay.by/v1/qrcode/getqrcode/?';
				}
				else
				{
					$url = 'https://api.express-pay.by/v1/qrcode/getqrcode/?';
				}
    			
    			$request_params_for_qr = array(
					 "Token" => $token,
					 "InvoiceId" => $_REQUEST['ExpressPayInvoiceNo'],
					 'ViewType' => 'base64'
				 );

				$request_params_for_qr["Signature"] = compute_signature($request_params_for_qr, $token, $secret_word, 'get_qr_code');
				 
				$request_params_for_qr  = http_build_query($request_params_for_qr);
				$response_qr = file_get_contents($url.$request_params_for_qr);
				$response_qr = json_decode($response_qr);
				$qr_code = $response_qr->QrCodeBody;
				$qr_description = Loc::getMessage("EPOS_QR_DESCRIPTION");

			$invoice_template = Loc::getMessage("EPOS_INVOICE_TEMPLATE");
						
			$epos_code  = CSalePaySystemAction::GetParamValue("EPOS_SERVICE_CODE") ."-";
			$epos_code  .= "1-";
			$epos_code .= $inv_id;

			$invoice_description = str_replace("##ORDER_ID##", $epos_code, $invoice_template);
			$invoice_description = str_replace("##SUM##", $out_summ, $invoice_description);
			$invoice_description = str_replace("##OR_CODE##", '<img src="data:image/jpeg;base64,' . $qr_code . '"  width="200" height="200"/>', $invoice_description);
			$invoice_description = str_replace("##OR_CODE_DESCRIPTION##", $qr_description, $invoice_description);
				
			$result = $invoice_description;


			echo $result;
	}
		else
		{
			echo Loc::getMessage("EPOS_INVOICE_ERROR");
		}
	}
	else
	{
		$isTest = CSalePaySystemAction::GetParamValue("EPOS_IS_TEST_API");
		$baseUrl = "https://api.express-pay.by/v1/";
		
		if($isTest == 'Y')
			$baseUrl = "https://sandbox-api.express-pay.by/v1/";
		
		$url = $baseUrl . "web_invoices";

		$request_params = getInvoiceParam();

		log_info('payment','REQUEST PARAMS: ' . json_encode($request_params));

		$button  = '<form id="expressPayForm" style="display:none;" method="POST" action="'.$url.'">';

        foreach($request_params as $key => $value)
        {
            $button .= "<input type='hidden' name='$key' value='$value'/>";
        }

        $button .= '<input type="submit" class="checkout_button" name="submit_button" value="Выставить счет в ЕРИП" />';
		$button .= '</form>';
		$button .= '<script>document.getElementById("expressPayForm").submit();</script>';

		log_info('payment','Button: ' . json_encode($button));
		
		echo $button;
	}
}

function getInvoiceParam()
{
	$order_id = $GLOBALS["SALE_INPUT_PARAMS"]["ORDER"]["ID"];
	$order_id = IntVal($order_id);
	$shouldPay = (strlen(CSalePaySystemAction::GetParamValue("SHOULD_PAY", '')) > 0) ? CSalePaySystemAction::GetParamValue("SHOULD_PAY", 0) : $GLOBALS["SALE_INPUT_PARAMS"]["ORDER"]["SHOULD_PAY"];
	$out_summ = number_format(floatval($shouldPay), 2, ',', '');

	$token = CSalePaySystemAction::GetParamValue("EPOS_TOKEN");
	$secret_word = CSalePaySystemAction::GetParamValue("EPOS_SECRET_WORD");
	$serviceId = CSalePaySystemAction::GetParamValue("EPOS_SERVICE_ID");
	$name_edit = CSalePaySystemAction::GetParamValue("EPOS_IS_NAME_EDITABLE");
	$name_edit = CSalePaySystemAction::GetParamValue("EPOS_IS_ADDRESS_EDITABLE");
	$amount_edit = CSalePaySystemAction::GetParamValue("EPOS_IS_AMOUNT_EDITABLE");

	$url = ((!empty($_SERVER['HTTPS'])) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

	$request_params = array(
		'ServiceId'         => $serviceId,
		'AccountNo'         => $order_id,
		'Amount'            => $out_summ,
		'Currency'          => 933,
		'ReturnType'        => 'redirect',
		'ReturnUrl'         => $url."&result=success&ExpressPayAmount={$out_summ}" ,
		'FailUrl'           => $url."&result=fail",
		'Expiration'        => '',
		'Info'              => $info,
		'Surname'           => '',
		'FirstName'         => '',
		'Patronymic'        => '',
		'Street'            => '',
		'House'             => '',
		'Apartment'         => '',
		'IsNameEditable'    => $name_edit == 'Y' ? 1 : 0,
		'IsAddressEditable' => $address_edit == 'Y' ? 1 : 0,
		'IsAmountEditable'  => $amount_edit == 'Y' ? 1 : 0,
		'EmailNotification' => '',
		'SmsPhone'          => ''
	);

	$request_params['Signature'] = compute_signature($request_params, $token, $secret_word);

	return $request_params;
}

function log_error_exception($name, $message, $e)
{
	expresspay_log($name, "ERROR" , $message . '; EXCEPTION MESSAGE - ' . $e->getMessage() . '; EXCEPTION TRACE - ' . $e->getTraceAsString());
}

function log_error($name, $message)
{
	expresspay_log($name, "ERROR" , $message);
}

function log_info($name, $message)
{
	expresspay_log($name, "INFO" , $message);
}

function expresspay_log($name, $type, $message)
{	
	$log_url = dirname(__FILE__) . '/log';

	if(!file_exists($log_url))
	{
		$is_created = mkdir($log_url, 0777);

		if(!$is_created)
			return;
	}

	$log_url .= '/express-pay-' . date('Y.m.d') . '.log';

	file_put_contents($log_url, $type . " - IP - " . $_SERVER['REMOTE_ADDR'] . "; USER AGENT - " . $_SERVER['HTTP_USER_AGENT'] . "; FUNCTION - " . $name . "; MESSAGE - " . $message . ';' . PHP_EOL, FILE_APPEND);
}

function compute_signature($request_params, $token, $secret_word, $method = 'add_invoice')
{
	$secret_word = trim($secret_word);
	$normalized_params = array_change_key_case($request_params, CASE_LOWER);
	$api_method = array( 
		'add_invoice' => array(
							"serviceid",
							"accountno",
							"amount",
							"currency",
							"expiration",
							"info",
							"surname",
							"firstname",
							"patronymic",
							"city",
							"street",
							"house",
							"building",
							"apartment",
							"isnameeditable",
							"isaddresseditable",
							"isamounteditable",
							"emailnotification",
							"smsphone",
							"returntype",
							"returnurl",
							"failurl"),
		'get_qr_code' => array(
							"invoiceid",
							"viewtype",
							"imagewidth",
							"imageheight"),
		'add_invoice_return' => array(
							"accountno",
							"invoiceno"
		)
	);

	$result = $token;

	foreach ($api_method[$method] as $item)
		$result .= ( isset($normalized_params[$item]) ) ? $normalized_params[$item] : '';

	$hash = strtoupper(hash_hmac('sha1', $result, $secret_word));

	return $hash;
}

function validSignature($signature)
{
	$token = CSalePaySystemAction::GetParamValue("EPOS_TOKEN");
	$secret_word = CSalePaySystemAction::GetParamValue("EPOS_SECRET_WORD");

	$signature_param = array(
		"AccountNo" => $_REQUEST['ExpressPayAccountNumber'],
		"InvoiceNo" => $_REQUEST['ExpressPayInvoiceNo'],
		);

	$validSignature = compute_signature($signature_param, $token, $secret_word, 'add_invoice_return');

	return $validSignature == $signature;
}