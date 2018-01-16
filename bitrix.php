<?
$url = 'http://crm.phytobiotics.ru/crm/rest/'; //ссылка на битрик crm
$login = 'test@test.tt'; //email аккаунта создателя
$pass = '1234567'; //пароль

$error = '';
$success = false;
$post = isset($_POST) && isset($_POST['form']) ? $_POST['form'] : array();

if (isset($post['save'])) {

	$fields = array();
	$fields['TITLE'] = 'Заполнена форма на '.$_SERVER['HTTP_HOST'];
	$fields['LOGIN'] = $login;
	$fields['PASSWORD'] = $pass;

	foreach ($post as $k => $v) {
		$fields[strtoupper($k)] = $v;
	}

	if (function_exists('curl_init')) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_VERBOSE, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
		$response = curl_exec($ch);
		$response = JsObjectToPhp($response, true); 
		curl_close($ch);
		if ($response['error'] == 201) {
			$success = true;
			$post = array();
		} else {
			$error = $response['error_message'];
		}
	} else {
		$error = 'Системная ошибка curl, сообщение не отправлено!';
	}

}


function JsObjectToPhp($data, $bSkipNative=false)
{
	$arResult = array();

	$bSkipNative |= !function_exists('json_decode');

	if(!$bSkipNative)
	{
		// json_decode recognize only UTF strings
		// the name and value must be enclosed in double quotes
		// single quotes are not valid
		$arResult = json_decode($data, true);

		if($arResult === null)
		{
			$bSkipNative = true;
		}
	}

	if ($bSkipNative)
	{
		$data = preg_replace('/[\s]*([{}\[\]\"])[\s]*/', '\1', $data);
		$data = trim($data);

		if (substr($data, 0, 1) == '{') // object
		{
			$arResult = array();

			$depth = 0;
			$end_pos = 0;
			$arCommaPos = array();
			$bStringStarted = false;
			$prev_symbol = "";

			$string_delimiter = '';
			for ($i = 1, $len = strlen($data); $i < $len; $i++)
			{
				$cur_symbol = substr($data, $i, 1);
				if ($cur_symbol == '"' || $cur_symbol == "'")
				{
					if (
						$prev_symbol != '\\' && (
							!$string_delimiter || $string_delimiter == $cur_symbol
						)
					)
					{
						if ($bStringStarted = !$bStringStarted)
							$string_delimiter = $cur_symbol;
						else
							$string_delimiter = '';

					}
				}

				elseif ($cur_symbol == '{' || $cur_symbol == '[')
					$depth++;
				elseif ($cur_symbol == ']')
					$depth--;
				elseif ($cur_symbol == '}')
				{
					if ($depth == 0)
					{
						$end_pos = $i;
						break;
					}
					else
					{
						$depth--;
					}
				}
				elseif ($cur_symbol == ',' && $depth == 0 && !$bStringStarted)
				{
					$arCommaPos[] = $i;
				}
				$prev_symbol = $cur_symbol;
			}

			if ($end_pos == 0)
				return false;

			$token = substr($data, 1, $end_pos-1);

			$arTokens = array();
			if (count($arCommaPos) > 0)
			{
				$prev_index = 0;
				foreach ($arCommaPos as $pos)
				{
					$arTokens[] = substr($token, $prev_index, $pos - $prev_index - 1);
					$prev_index = $pos;
				}
				$arTokens[] = substr($token, $prev_index);
			}
			else
			{
				$arTokens[] = $token;
			}

			foreach ($arTokens as $token)
			{
				$arTokenData = explode(":", $token, 2);

				$q = substr($arTokenData[0], 0, 1);
				if ($q == '"' || $q == '"')
					$arTokenData[0] = substr($arTokenData[0], 1, -1);
				$arResult[JsObjectToPhp($arTokenData[0], true)] = JsObjectToPhp($arTokenData[1], true);
			}
		}
		elseif (substr($data, 0, 1) == '[') // array
		{
			$arResult = array();

			$depth = 0;
			$end_pos = 0;
			$arCommaPos = array();
			$bStringStarted = false;
			$prev_symbol = "";
			$string_delimiter = "";

			for ($i = 1, $len = strlen($data); $i < $len; $i++)
			{
				$cur_symbol = substr($data, $i, 1);
				if ($cur_symbol == '"' || $cur_symbol == "'")
				{
					if (
						$prev_symbol != '\\' && (
							!$string_delimiter || $string_delimiter == $cur_symbol
						)
					)
					{
						if ($bStringStarted = !$bStringStarted)
							$string_delimiter = $cur_symbol;
						else
							$string_delimiter = '';

					}
				}
				elseif ($cur_symbol == '{' || $cur_symbol == '[')
					$depth++;
				elseif ($cur_symbol == '}')
					$depth--;
				elseif ($cur_symbol == ']')
				{
					if ($depth == 0)
					{
						$end_pos = $i;
						break;
					}
					else
					{
						$depth--;
					}
				}
				elseif ($cur_symbol == ',' && $depth == 0 && !$bStringStarted)
				{
					$arCommaPos[] = $i;
				}
				$prev_symbol = $cur_symbol;
			}

			if ($end_pos == 0)
				return false;

			$token = substr($data, 1, $end_pos-1);

			if (count($arCommaPos) > 0)
			{
				$prev_index = 0;
				foreach ($arCommaPos as $pos)
				{
					$arResult[] = JsObjectToPhp(substr($token, $prev_index, $pos - $prev_index - 1), true);
					$prev_index = $pos;
				}
				$r = JsObjectToPhp(substr($token, $prev_index), true);
				if (isset($r))
					$arResult[] = $r;
			}
			else
			{
				$r = JsObjectToPhp($token, true);
				if (isset($r))
					$arResult[] = $r;
			}
		}
		elseif ($data === "")
		{
			return null;
		}
		else // scalar
		{
			$q = substr($data, 0, 1);
			if ($q == '"' || $q == "'")
				$data = substr($data, 1, -1);

			//\u0412\u0430\u0434\u0438\u043c
			if(strpos($data, '\u') !== false)
				$data = preg_replace_callback("/\\\u([0-9A-F]{2})([0-9A-F]{2})/i", 'customDecodeUtf16', $data);

			$arResult = $data;
		}
	}

	return $arResult;
}

function customDecodeUtf16 ($ch)
{
	$res = chr(hexdec($ch[2])).chr(hexdec($ch[1]));
	return iconv('UTF-16', 'UTF-8', $res);
}
?>
