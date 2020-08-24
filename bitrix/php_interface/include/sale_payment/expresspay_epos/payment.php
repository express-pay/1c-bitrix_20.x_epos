<?
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule("sale");

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

// Отправка POST запроса
if (!function_exists('ExpressPay_SendRequestPOST'))
{
	function ExpressPay_SendRequestPOST($url, $params)
	{	
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
	
		return $httpcode;
	}
}
if (!function_exists('ExpressPay_SendRequestGET'))
{
function ExpressPay_SendRequestGET($url){
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	$response = curl_exec($ch);
	curl_close($ch);
	return $response;	
}
}

if (!function_exists('ExpressPay_AddInvoice'))
{
	// Формирование цифровой подписи
	function computeSignature($requestParams, $secretWord, $method)
	{
		$normalizedParams = array_change_key_case($requestParams, CASE_LOWER);
		$mapping = array(
			"add-invoice" => array(
									"token",
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
									"isamounteditable")
		);
		$apiMethod = $mapping[$method];
		$result = "";
		
		foreach ($apiMethod as $item)
		{
			$result .= $normalizedParams[$item];
		}
		$hash = strtoupper(hash_hmac('sha1', $result, $secretWord));
		
		return $hash;
	}
	
	//Выставление счета
	function ExpressPay_AddInvoice($token, $numberAccount, $amount, $currency, $expiration = "", $info = "", 
		$surname = "", $firstName = "", $patronymic = "", $city = "", $street = "", $house="", $building = "", 
		$apartment = "", $isNameEditable = "", $isAddressEditable = "", $isAmountEditable = "", $emailNotification = "")
	{	
		$isTest = CSalePaySystemAction::GetParamValue("EPOS_IS_TEST_API");
		$baseUrl = "https://api.express-pay.by/v1/";
		
		if($isTest == 'Y')
			$baseUrl = "https://sandbox-api.express-pay.by/v1/";
		
		$url = $baseUrl . "invoices?token=" . $token;
		
		$requestParams = array(
				"AccountNo" => $numberAccount,
				"Amount" => $amount,
				"Currency" => $currency,
				"Expiration" => $expiration,
				"Info" => $info,
				"Surname" => $surname,
				"FirstName" => $firstName,
				"Patronymic" => $patronymic,
				"City" => $city,
				"Street" => $street,
				"House" => $house,
				"Building" => $building,
				"Apartment" => $apartment,
				"IsNameEditable" => $isNameEditable,
				"IsAddressEditable" => $isAddressEditable,
				"IsAmountEditable" => $isAmountEditable,
				"EmailNotification" => $emailNotification
		);
		return ExpressPay_SendRequestPOST($url, $requestParams);  
	}
}

log_info('payment','Begin payment process');


if ($_SERVER['REQUEST_METHOD'] === 'GET')
{
	if(isset($_REQUEST['result']))
	{
		if($_REQUEST['result'] == 'success' && validSignature($_REQUEST['Signature']))
		{
			$inv_id = $_REQUEST['ExpressPayAccountNumber'];
			$out_summ = $_REQUEST['ExpressPayAmount'];
			$paname = CSalePaySystemAction::GetParamValue("EPOS_PERSONAL_ACCOUNT_NAME");

			
				$token = CSalePaySystemAction::GetParamValue("ERIP_TOKEN");
				$secret_word = CSalePaySystemAction::GetParamValue("ERIP_SECRET_WORD");
    			
    			$request_params_for_qr = array(
					 "Token" => $token,
					 "InvoiceId" => $_REQUEST['ExpressPayInvoiceNo'],
					 'ViewType' => 'base64'
				 );

				$request_params_for_qr["Signature"] = compute_signature($request_params_for_qr, $token, $secret_word, 'get_qr_code');
				 
				$request_params_for_qr  = http_build_query($request_params_for_qr);
				$response_qr = ExpressPay_SendRequestGET('https://api.express-pay.by/v1/qrcode/getqrcode/?'.$request_params_for_qr );
				$response_qr = json_decode($response_qr);
				$qr_code = $response_qr->QrCodeBody;
				$qr_description = 'Отсканируйте QR-код для оплаты';

			$invoice_template = 
			'<table style="width: 100%;text-align: left;">
            <tbody>
                    <tr>
                        <td valign="top" style="text-align:left;">
                            Вам необходимо произвести платеж в любой системе, позволяющей проводить оплату через ЕРИП (пункты банковского обслуживания, банкоматы, платежные терминалы, системы интернет-банкинга, клиент-банкинга и т.п.).
                            <br />
                            <br /> 1. Для этого в перечне услуг ЕРИП перейдите в раздел: <br />
							<b>Сервис E-POS-&gt;E-POS - оплата товаров и услуг</b><br />
							<br /> либо В поле "Код" введите <b>4440631</b> и нажмите "Продолжить"<br />
                            <br /> 2. В поле \'<b>##PERSONAL_ACCOUNT_NAME##</b>\' введите \'<b>##ORDER_ID##</b>\' и нажмите \'Продолжить\'. <br />
                            <br /> 3. Укажите сумму для оплаты <b>##SUM##</b>
                        </td>
                            <td style="text-align: center;padding: 0px 20px 0 0;vertical-align: middle">
								##OR_CODE##
								<p><b>##OR_CODE_DESCRIPTION##</b></p>
								</br>
								</br>
								</td>
						</tr>
				</tbody>
			</table>';
															
			$invoice_description = str_replace("##ORDER_ID##", $inv_id, $invoice_template);
			$invoice_description = str_replace("##SUM##", $out_summ, $invoice_description);
			$invoice_description = str_replace("##PERSONAL_ACCOUNT_NAME##", $paname, $invoice_description);
			//$invoice_description = str_replace("##ERIP_PATH##", $erip_path, $invoice_description);
			$invoice_description = str_replace("##OR_CODE##", '<img src="data:image/jpeg;base64,' . $qr_code . '"  width="200" height="200"/>', $invoice_description);
			$invoice_description = str_replace("##OR_CODE_DESCRIPTION##", $qr_description, $invoice_description);
				
			$result = $invoice_description;


			echo $result;
	}
		else
		{
			echo 'При попытке выставить счет произошла ошибка.';
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
	$order_id = $GLOBALS["SALE_INPUT_PARAMS"]["ORDER"]["ID"];//Номер заказа
	$order_id = IntVal($order_id);
	$shouldPay = (strlen(CSalePaySystemAction::GetParamValue("SHOULD_PAY", '')) > 0) ? CSalePaySystemAction::GetParamValue("SHOULD_PAY", 0) : $GLOBALS["SALE_INPUT_PARAMS"]["ORDER"]["SHOULD_PAY"];
	$out_summ = number_format(floatval($shouldPay), 2, ',', '');//Формирование суммы с 2 числами после ","

	$token = CSalePaySystemAction::GetParamValue("EPOS_TOKEN");
	$secret_word = CSalePaySystemAction::GetParamValue("EPOS_SECRET_WORD");
	$serviceId = CSalePaySystemAction::GetParamValue("EPOS_SERVICE_ID");
	$info_template = CSalePaySystemAction::GetParamValue("EPOS_INFO_TEMPLATE");
	$info = str_replace("##ORDER_ID##", $order_id, $info_template);
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
?>