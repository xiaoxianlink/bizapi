<?php

namespace Bizapi\Controller;

use Common\Controller\BizapibaseController;
use Think\Log;

class ApiController extends BizapibaseController {

	static $channel = 99;
	
	public function sendJsonResult($code, $passthruQuery, $handleTime, $json_option=0){
		$data = array (
			'fanhuicode' => $code,
			'touchuanstring' => $passthruQuery,
			'timestamp' => time(),
			'handletime' => $handleTime
			);
		header('Content-Type:application/json; charset=utf-8');
        exit(json_encode($data, $json_option));
	}
	
	function index() {
		$this->display();
	}
	
	function dummy(){
		$log = new Log();
		$log->write(json_encode($_REQUEST));
		header('Content-Type:application/json; charset=utf-8');
		exit(json_encode($_REQUEST));
	}
	
	function dingyue() {
		$log = new Log();
		$log->write(json_encode($_REQUEST));
		// read input
		$licRegion = $_REQUEST ['chepaitou'];
		$licNumber = $_REQUEST ['chepaihao'];
		$frameNumber = $_REQUEST ['chejiahao'];
		$engineNumber = $_REQUEST ['fadongjihao'];
		$holderName = $_REQUEST ['chezhumingcheng'];
		$holderPhone = $_REQUEST ['chezhudianhua'];
		$appId = $_REQUEST ['APPID'];
		$appKey = $_REQUEST ['APPKEY'];
		$timestamp = $_REQUEST ['timestamp'];
		$sign = $_REQUEST ['qianming'];
		$passthruQuery = $_REQUEST ['touchuanstring'];
		// valid input
		if($licRegion == "" || $licNumber == "" || $frameNumber == "" || $engineNumber == ""){
			$this->sendJsonResult(2003, $passthruQuery, 0);
		}
		$licRegion = urldecode($licRegion);
		if($timestamp == "" || $sign == ""){
			$this->sendJsonResult(2004, $passthruQuery, 0);
		}
		if($appId == "" || $appKey == ""){
			$this->sendJsonResult(2005, $passthruQuery, 0);
		}
		
		$verify = md5($appId . $licRegion . $licNumber . $frameNumber . $engineNumber . $timestamp . $appKey);
		if($verify != $sign ){
			$this->sendJsonResult(3005, $passthruQuery, 0);
		}
		
		$pattern = '/^[A-Za-z0-9]*$/';
		$matches = null;
		if (preg_match($pattern, $licNumber, $matches) == 0 || strlen($licNumber) != 6) {
			$this->sendJsonResult(3002, $passthruQuery, 0);
		}
		if (preg_match($pattern, $frameNumber, $matches) == 0|| strlen($frameNumber) < 15) {
			$this->sendJsonResult(3004, $passthruQuery, 0);
		}
		if (preg_match($pattern, $engineNumber, $matches) == 0) {
			$this->sendJsonResult(3003, $passthruQuery, 0);
		}
		$region_model = M ( "region" );
		$region = $region_model->where("nums = '$licRegion'")->find();
		if(empty($region)){
			$this->sendJsonResult(3001, $passthruQuery, 0);
		}
		
		$bizapi_model = M ( "bizapi" );
		$now = time ();
		$bizapi = $bizapi_model -> where(" app_id = '$appId' and state = 1 and expiration_time >= $now ") ->find();
		if(empty($bizapi)){
			$this->sendJsonResult(2001, $passthruQuery, 0);
		}
		else{
			if($bizapi['app_key'] != $appKey){
				$this->sendJsonResult(2002, $passthruQuery, 0);
			}
		}
		
		// start transaction
		$_start = microtime(TRUE);
		
		// save car if not exist, any channel
		$data = array ();
		$data ['license_number'] = $licRegion . strtoupper ( $licNumber );
		$data ['frame_number'] = strtoupper ( $frameNumber );
		$data ['engine_number'] = strtoupper ( $engineNumber );
		$car_model = M ( "Car" );
		$car = $car_model->where ( $data )->find ();
		$newcar = false;
		if (empty ( $car )) {
			$data ['create_time'] = time ();
			$data ['channel'] = self::$channel;
			$data ['channel_key'] = "BIZAPI_" . $bizapi["id"];
			$car_model->add ( $data );
			$car_id = $car_model->getLastInsID ();
			$newcar = true;
		}
		else{
			$car_id = $car["id"];
		}
		
		// create the bizapi user
		$user_model = D ( "User" );
		$data = array ();
		$data ['group_id'] = 90;
		$username = "BIZAPI_USER_" . time();
		$data ['username'] = $username;
		$data ['nickname'] = $username;
		$openid = md5($username);
		$data ['openid'] = $openid;
		$data ['is_att'] = 0;
		$data ['create_time'] = time ();
		$data ['channel'] = self::$channel;
		$data ['channel_key'] = "BIZAPI_" . $bizapi["id"];
		$user_model->add ( $data );
		$user_id = $user_model->getLastInsID ();
		
		// check the bizapi has already subcribe the car, create new user if not.
		$uc_model = M ( "User_car" );
		$data = array (
			"user_id" => $user_id,
			"car_id" => $car_id,
			"is_sub" => 0,
			'create_time' => time () 
			);
		$uc_model->add ( $data );
		
		// end transaction
		$_end = microtime(TRUE);
		$_handle = number_format($_end - $_start, 4);
		
		$data = array (
			"last_time" => time()
			);
		$bizapi_model -> where(" id = {$bizapi['id']}") -> save($data);
		
		if($newcar){
			$this->scan_and_send($car_id, $licRegion, $licNumber, $openid, $bizapi['app_domain']);
		}
		else{
			$this->send($car_id, $licRegion, $licNumber, $openid, $bizapi['app_domain']);
		}
		$this->sendJsonResult(1000, $passthruQuery, $_handle);
	}
	
	function scan_and_send($car_id, $licRegion, $licNumber, $openid, $bizapi_app_domain){
		$this->scan_api ( $car_id);
		$this->send($car_id, $licRegion, $licNumber, $openid, $bizapi_app_domain);
	}
	
	function scan_api($car_id){
		$scan_api_url = "";
		if(runEnv == "production"){
			$scan_api_url = "http://ziniu.xiaoxianlink.com/index.php?g=weizhang&m=index&a=index"; 
		}
		elseif(runEnv == "test"){
			$scan_api_url = "http://zndev.xiaoxianlink.com/index.php?g=weizhang&m=index&a=index"; 
		}
		else{
			$scan_api_url = "http://zn.xiaoxian.com/index.php?g=weizhang&m=index&a=index";
		}
		$post_data = array(
			"car_id" => $car_id
			);
		$this->request_post($scan_api_url, http_build_query($post_data));
	}
	
	function send($car_id, $licRegion, $licNumber, $openid, $bizapi_app_domain){
		$log = new Log();
		$date = date ( 'Y-m-d' );
		$callbackUrl = "";
		if(runEnv == 'production'){
			$callbackUrl = "http://weixin.xiaoxianlink.com/";
		}
		elseif(runEnv == "test"){
			$callbackUrl = "http://wxdev.xiaoxianlink.com/";
		}
		else{
			$callbackUrl = "http://wx.xiaoxian.com/";
		}
		$post_data = array (
			'chepai' => $licRegion . strtoupper ( $licNumber ),
			'weizhangzongshu' => 0,
			'fafenzongshu' => 0,
			'fakuanzongshu' => 0,
			'url_weizhangliebiao' => $callbackUrl . "index.php?g=weixin&m=scan&a=index&openid=".$openid."&carid=". $car_id,
			'timestamp' => time()
			);

		$endorsement_model = M ( "Endorsement" );
		$where = array (
				"car_id" => $car_id,
				"is_manage" => 0 
		);
		$endorsement = $endorsement_model->field ( "count(*) as nums, sum(points) as all_points, sum(money) as all_money" )->where ( $where )->find ();
		
		if (! empty ( $endorsement )) {
			if ($endorsement ['nums'] != 0) {
				$post_data['weizhangzongshu'] = $endorsement ['nums'];
				$post_data['fafenzongshu'] = $endorsement['all_points'];
				$post_data['fakuanzongshu'] = $endorsement['all_money'];
			}
		}
		
		$target_url = $bizapi_app_domain;
		if(false === strpos($target_url, 'http://')){
			$target_url = "http://" . $target_url;
		}
		$target_url = $target_url . "/api/weizhang/weizhangtongji";
		$log->write ( "url=" .$target_url, 'DEBUG', '', dirname ( $_SERVER ['SCRIPT_FILENAME'] ) . '/Logs/Bizapi/' . date ( 'y_m_d' ) . '.log' );
		$log->write ( serialize( http_build_query($post_data) ) , 'DEBUG', '', dirname ( $_SERVER ['SCRIPT_FILENAME'] ) . '/Logs/Bizapi/' . date ( 'y_m_d' ) . '.log' );
		$log->write ( "callback:" . $post_data['url_weizhangliebiao'], 'DEBUG', '', dirname ( $_SERVER ['SCRIPT_FILENAME'] ) . '/Logs/Bizapi/' . date ( 'y_m_d' ) . '.log' );
		$_start = microtime(TRUE);
		$dataRes = $this->request_post($target_url, http_build_query($post_data));
		$_end = microtime(TRUE);
		$_handle = number_format($_end - $_start, 4);
		$log->write ( "handle=" .$_handle, 'DEBUG', '', dirname ( $_SERVER ['SCRIPT_FILENAME'] ) . '/Logs/Bizapi/' . date ( 'y_m_d' ) . '.log' );
		$log->write ( serialize ( $dataRes ), 'DEBUG', '', dirname ( $_SERVER ['SCRIPT_FILENAME'] ) . '/Logs/Bizapi/' . date ( 'y_m_d' ) . '.log' );
		
		$data = array (
			"from_userid" => 0,
			"openid" => $openid,
			"msg_type" => 1,
			"tar_id" => $car_id,
			"create_time" => time (),
			"nums" => $post_data['weizhangzongshu'],
			"all_points" => $post_data['fafenzongshu'],
			"all_money" => $post_data['fakuanzongshu'] 
			);
		$model = M ( "Message" );
		$model->add ( $data );
	}
	
	function orderQuery() {
		// read input
		$orderSN = $_REQUEST ['dingdanhao'];
		$appId = $_REQUEST ['APPID'];
		$appKey = $_REQUEST ['APPKEY'];
		$timestamp = $_REQUEST ['timestamp'];
		//$sign = $_REQUEST ['qianming'];
		$passthruQuery = $_REQUEST ['touchuanstring'];
		// valid input
		if($orderSN == ""){
			$this->sendJsonResult(4001, $passthruQuery, 0);
		}
		if($timestamp == ""){
			$this->sendJsonResult(2004, $passthruQuery, 0);
		}
		if($appId == "" || $appKey == ""){
			$this->sendJsonResult(2005, $passthruQuery, 0);
		}
		/*
		$verify = md5($appId . $orderSN . $timestamp . $appKey);
		if($verify != $sign ){
			$this->sendJsonResult(3005, $passthruQuery, 0);
		}
		*/
		$bizapi_model = M ( "bizapi" );
		$now = time();
		$bizapi = $bizapi_model -> where(" app_id = '$appId' and state = 1 and expiration_time >= $now ") ->find();
		if(empty($bizapi)){
			$this->sendJsonResult(2001, $passthruQuery, 0);
		}
		else{
			if($bizapi['app_key'] != $appKey){
				$this->sendJsonResult(2002, $passthruQuery, 0);
			}
		}
		
		// start transaction
		$_start = microtime(TRUE);
		
		$order_model = M("order");
		$order = $order_model->where("order_sn = $orderSN") -> find();
		if(empty($order)){
			$this->sendJsonResult(4001, $passthruQuery, 0);
		}
		
		$_model = M("");
		$_result = $_model->query("select c.license_number, e.* from cw_endorsement e, cw_car c where e.id = {$order['endorsement_id']} and c.id = {$order['car_id']}");
		$result = $_result[0];
		
		// end transaction
		$_end = microtime(TRUE);
		$_handle = number_format($_end - $_start, 4);
		
		$order_status_desc = "未处理";
		if($order['order_status'] == 3 ){
			$order_status_desc = "处理中";
		}
		if($order['order_status'] == 4 ){
			$order_status_desc = "已处理";
		}

		$data = array (
			"dingdanhao" => $orderSN,
			"dingdantime" => $order['c_time'],
			"dingdanzhuangtai" => $order_status_desc,
			"dingdanjine" => ($order['pay_money'] != null)?$order['pay_money']:$order['money'],
			"chepai" => $result['license_number'],
			"weizhangtime" => $result['time'],
			"weizhangcity" => $result['area'],
			"weizhangcode" => $result['code'],
			"fajin" => $result['money'],
			"fafen" => $result['points'],
			"timestamp" => time (),
			"touchuangstring" => ($passthruQuery)?$passthruQuery:""
			);
			
		$this->sendOrderJsonResult($data, $passthruQuery, $_handle);
	}
	
	public function sendOrderJsonResult($order, $passthruQuery, $handleTime, $json_option=0){
		$data = array (
			'fanhuiCode' => 4000,
			'Touchuanstring' => ($passthruQuery)?$passthruQuery:"",
			'timestamp' => time(),
			'Handletime' => $handleTime,
			'dingdanxinxi' => $order
		);
		header('Content-Type:application/json; charset=utf-8');
        exit(json_encode($data, $json_option));
	}
	
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
	
	function scan_test(){
		$car_id = $_REQUEST ['car_id'];
		$bizapi_id = $_REQUEST ['bizapi_id'];
		$user_id = $_REQUEST ['user_id'];
		
		$car_model = M ( "Car" );
		$car = $car_model->where ( "id = $car_id" )->find ();
		
		$bizapi_model = M ( "bizapi" );
		$bizapi = $bizapi_model->where ( "id = $bizapi_id" )->find ();
		
		$user_model = M ( "User" );
		$user = $user_model->where ( "id = $user_id" )->find ();
		
		$this->scan_and_send($car_id, mb_substr ( $car ['license_number'], 0, 2, 'utf-8' ), substr($car['license_number'], 2), $user['openid'], $bizapi['app_domain'] );
	}
	
	function send_test(){
		$car_id = $_REQUEST ['car_id'];
		$bizapi_id = $_REQUEST ['bizapi_id'];
		$user_id = $_REQUEST ['user_id'];
		
		$car_model = M ( "Car" );
		$car = $car_model->where ( "id = $car_id" )->find ();
		
		$bizapi_model = M ( "bizapi" );
		$bizapi = $bizapi_model->where ( "id = $bizapi_id" )->find ();
		
		$user_model = M ( "User" );
		$user = $user_model->where ( "id = $user_id" )->find ();
		
		$log = new Log();
		$log->write("license_number=".$license_number );
		$this->send($car_id, mb_substr ( $car ['license_number'], 0, 2, 'utf-8' ), substr($car['license_number'], 2), $user['openid'], $bizapi['app_domain'] );
	}

}