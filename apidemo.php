<?php

function request_post($url = '', $param = '') {
	if (empty ( $url ) || empty ( $param )) {
		return false;
	}
	$postUrl = $url;
	$curlPost = $param;
	$ch = curl_init (); // 
	curl_setopt ( $ch, CURLOPT_URL, $postUrl ); 
	curl_setopt ( $ch, CURLOPT_HEADER, 0 ); 
	curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 ); 
	curl_setopt ( $ch, CURLOPT_POST, 1 ); 
	curl_setopt ( $ch, CURLOPT_POSTFIELDS, $curlPost );
	$data = curl_exec ( $ch ); 
	curl_close ( $ch );
	return $data;
}

//$appId = "dummy";
//$appKey = "2da436ac7e772a06619cc3f302e81c9f";
$appId = "yanger";
$appKey = "4a3dec1da38d274a7e0d5656712964b6";

$licRegion = "苏";
$licNumber = "AZD760";
$frameNumber = "8488s8r3eee34561";
$engineNumber = "100864545400";
$holderName = "10086";
$holderPhone = "13959110086";
//$timestamp = time();
$timestamp = "1234";
$sign = md5($appId . $licRegion . $licNumber . $frameNumber . $engineNumber . $timestamp . $appKey);
$passthruQuery = "apidemo";

$post_data = array (
		"chepaitou" => $licRegion,
		"chepaihao" => $licNumber,
		"chejiahao" => $frameNumber,
		"fadongjihao" => $engineNumber,
		"APPID" => $appId,
		"APPKEY" => $appKey,
		"chezhumingcheng" => $holderName,
		"chezhudianhua" => $holderPhone,
		"timestamp" => $timestamp,
		"qianming" => $sign,
		"touchuanstring" => $passthruQuery
	);

$target_url = "http://testapi.xiaoxianlink.com/api/weizhang/cheliangdingyue";
//$result = request_post($target_url, $post_data);
//echo $result; 
echo $sign;
?>