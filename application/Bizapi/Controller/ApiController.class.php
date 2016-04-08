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
		if (empty ( $car )) {
			$data ['create_time'] = time ();
			$data ['channel'] = self::$channel;
			$data ['channel_key'] = "BIZAPI_" . $bizapi["id"];
			$car_model->add ( $data );
			$car_id = $car_model->getLastInsID ();
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
		
		$this->scan_and_send($car_id, $licRegion, $licNumber, $openid, $bizapi['app_domain']);
		
		$this->sendJsonResult(1000, $passthruQuery, $_handle);
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
	
	function scan_and_send($car_id, $licRegion, $licNumber, $openid, $bizapi_app_domain){
		$log = new Log();
		$log->write("licRegion=".$licRegion );
		$log->write("licNumber=".$licNumber );
		$city = $licRegion . substr($licNumber, 0, 1);
		$region_model = M ( "Region" );
		$region = $region_model->where ( "nums = '$city'" )->find ();
		if (! empty ( $region )) {
			$this->scan_api ( $car_id, $region ['city'] );
		}
		$this->send($car_id, $licRegion, $licNumber, $openid, $bizapi_app_domain);
	}
	
	function send($car_id, $licRegion, $licNumber, $openid, $bizapi_app_domain){
		$log = new Log();
		$date = date ( 'Y-m-d' );
		$post_data = array (
			'chepai' => $licRegion . strtoupper ( $licNumber ),
			'weizhangzongshu' => 0,
			'fafenzongshu' => 0,
			'fakuanzongshu' => 0,
			'url_weizhangliebiao' => "http://wxdev.xiaoxianlink.com/index.php?g=weixin&m=scan&a=index&openid=".$openid."&carid=". $car_id,
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
	
	function scan_api($car_id, $city, $type = 1) {
		$log = new Log ();
		$log->write("car_id=$car_id, city=$city");
		$car_model = M ( "Car" );
		$car = $car_model->where ( "id = $car_id" )->find ();
		$region_model = M ( "Region" );
		if ($type == 1) {
			$region = $region_model->where ( "city = '$city'" )->find ();
		} else {
			$region = $region_model->where ( "province = '$city' and level = 1" )->find ();
		}
		
		$app_id = app_id;
		$app_key = app_key;
		$engineLen = $region ['c_engine_nums'];
		if ($engineLen > 0) {
			$engine_number = substr ( $car ['engine_number'], - $engineLen );
		} else {
			$engine_number = $car ['engine_number'];
		}
		$frameLen = $region ['c_frame_nums'];
		if ($frameLen > 0) {
			$frame_number = substr ( $car ['frame_number'], - $frameLen );
		} else {
			$frame_number = $car ['frame_number'];
		}
		$car = "{hphm={$car['license_number']}&classno={$frame_number}&engineno={$engine_number}&city_id={$region['code']}&car_type=02}";
		$car_info = urlencode ( $car );
		$time = time ();
		$sign = md5 ( $app_id . $car . $time . $app_key );
		$url = "http://www.cheshouye.com/api/weizhang/query_task?car_info=$car_info&sign=$sign&timestamp=$time&app_id=$app_id";
		$ch = curl_init ();
		curl_setopt ( $ch, CURLOPT_URL, $url );
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, FALSE );
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
		$output = curl_exec ( $ch );
		curl_close ( $ch );
		$jsoninfo = json_decode ( $output, true );
		$log->write ( "请求参数：" . $url, 'DEBUG', '', dirname ( $_SERVER ['SCRIPT_FILENAME'] ) . '/Logs/Bizapi/' . date ( 'y_m_d' ) . '.log' );
		$log->write ( "返回参数：" . $output, 'DEBUG', '', dirname ( $_SERVER ['SCRIPT_FILENAME'] ) . '/Logs/Bizapi/' . date ( 'y_m_d' ) . '.log' );
		$endorsement_model = M ( "Endorsement" );
		$log_model = M ( "Endorsement_log" );
		$ids = "0";
		$jilu_model = M ( "endorsement_jilu" );
		$jilu_data = array (
			"car_id" => $car_id,
			"city" => $city,
			"money" => 0,
			"points" => 0,
			"all_nums" => 0,
			"add_nums" => 0,
			"edit_nums" => 0,
			"c_time" => time (),
			"port" => 'cheshouye.com',
			"code" => $jsoninfo ['status'],
			"state" => 1 
			);
		$jilu_id = $jilu_model->add ( $jilu_data );
		if ($jsoninfo ['status'] == 2001) {
			foreach ( $jsoninfo ['historys'] as $v ) {
				$jilu_data ['all_nums'] ++;
				$jilu_data ['money'] += $v ['money'];
				$jilu_data ['points'] += $v ['fen'];
				$time = strtotime ( $v ['occur_date'] );
				$endorsement = $endorsement_model->where ( "car_id = '$car_id' and time = '$time'" )->find ();
				if (empty ( $endorsement )) {
					$city = isset ( $v ['city_name'] ) ? $v ['city_name'] : $city;
					$data = array (
						"car_id" => $car_id,
						"area" => $city,
						"query_port" => csyapi,
						"code" => $v ['code'],
						"time" => $time,
						"money" => $v ['money'],
						"points" => $v ['fen'],
						"address" => $v ['occur_area'],
						"content" => $v ['info'],
						"create_time" => time (),
						"manage_time" => time (),
						"query_no" => $jilu_id,
						// "certificate_no" => $v ['archive'],
						"office" => $v ['officer'] 
						);
					$endorsement_model->add ( $data );
					$jilu_data ['add_nums'] ++;
					$data = array (
						"end_id" => $endorsement_model->getLastInsID (),
						"state" => 1,
						"c_time" => time (),
						"type" => 0 
						);
					$log_model->add ( $data );
				}
			}
			$jilu_model->where ( "id='$jilu_id'" )->save ( $jilu_data );
		} elseif ($jsoninfo ['status'] == 2000) {
		} elseif($jsoninfo ['status'] == 5008){
			$car_scan_data = array (
				"scan_state" => 0,
				"scan_state_desc" => "输入的车辆信息有误，请查证后重新输入",
				"scan_state_time" => time (),
				"scan_stop_query" => $jilu_id
			);
			$car_model->where ( "id='$car_id'" )->save ( $car_scan_data );
		}else {
			$jsoninfo = $this->get_endorsement ( $car_id, $city );
			$jilu_data = array (
				"car_id" => $car_id,
				"city" => $city,
				"money" => 0,
				"points" => 0,
				"all_nums" => 0,
				"add_nums" => 0,
				"edit_nums" => 0,
				"c_time" => time (),
				"port" => "http://120.26.57.239/api/",
				"code" => $jsoninfo ['code'],
				"state" => 1
				);
			$jilu_id = $jilu_model->add ( $jilu_data );
			if ($jsoninfo ['code'] == '0') {
				foreach ( $jsoninfo ['data'] [0] ['result'] as $v ) {
					$v ['violationPrice'] = isset($v ['violationPrice']) ? $v ['violationPrice'] : 0;
					$v ['violationMark'] = isset($v ['violationMark']) ? $v ['violationMark'] : '-1';
					$v ['violationTime'] = isset($v ['violationTime']) ? $v ['violationTime'] : '-1';
					$v ['violationCode'] = isset($v ['violationCode']) ? $v ['violationCode'] : 0;
					$v ['violationAddress'] = isset($v ['violationAddress']) ? $v ['violationAddress'] : '-1';
					$v ['violationDesc'] = isset($v ['violationDesc']) ? $v ['violationDesc'] : '-1';
					if ($v ['violationPrice'] != 0 && $v ['violationMark'] != '-1' && $v ['violationTime'] != '-1' && $v ['violationCode'] != 0 && $v ['violationAddress'] != '-1' && $v ['violationDesc'] != '-1') {
						$v ['violationPrice'] = $v ['violationPrice'] / 100;
						$jilu_data ['all_nums'] ++;
						$jilu_data ['money'] += $v ['violationPrice'];
						$jilu_data ['points'] += $v ['violationMark'];
						$time = strtotime ( $v ['violationTime'] );
						$endorsement = $endorsement_model->where ( "car_id = '$car_id' and time = '$time'" )->find ();
						if (empty ( $endorsement )) {
							$city = isset ( $v ['violationCity'] ) ? $v ['violationCity'] : $city;
							$data = array (
								"car_id" => $car_id,
								"area" => $city,
								"query_port" => acfapi,
								"code" => $v ['violationCode'],
								"time" => $time,
								"money" => $v ['violationPrice'],
								"points" => $v ['violationMark'],
								"address" => $v ['violationAddress'],
								"content" => $v ['violationDesc'],
								"create_time" => time (),
								"manage_time" => time (),
								"query_no" => $jilu_id,
								// "certificate_no" => $v ['archive'],
								"office" => $v ['officeName']
								);
							$endorsement_model->add ( $data );
							$jilu_data ['add_nums'] ++;
							$data = array (
								"end_id" => $endorsement_model->getLastInsID (),
								"state" => 1,
								"c_time" => time (),
								"type" => 0
								);
							$log_model->add ( $data );
						}
					}
				}
			}
			elseif($jsoninfo ['code'] == 29 || ($jsoninfo ['code'] >= 31 && $jsoninfo ['code'] <= 34)){
				$car_scan_data = array (
					"scan_state" => 0,
					"scan_state_desc" => $jsoninfo['message'],
					"scan_state_time" => time (),
					"scan_stop_query" => $jilu_id
					);
				$car_model->where ( "id='$car_id'" )->save ( $car_scan_data );
			}
			$jilu_model->where ( "id='$jilu_id'" )->save ( $jilu_data );
		}
		$data = array (
				"last_time" => time () 
		);
		$car_model->where ( "id = '$car_id'" )->save ( $data );
	}
	
	function get_endorsement($car_id, $city) {
		$log = new Log ();
		$car_model = M ( "Car" );
		$car = $car_model->where ( "id = $car_id" )->find ();
		$region_model = M ( "Region" );
		$region = $region_model->where ( "city = '$city'" )->find ();
		/*
		 * $acf_model = M ( "Acf_token" ); $acf = $acf_model->find (); if (empty ( $acf )) { $token = $this->get_acf_token (); $data = array ( "token" => $token, "c_time" => time () ); $acf_model->add ( $data ); } else {
		 */
		// if ($acf ['token'] == '' || $acf ['token'] == null || $acf ['c_time'] < (time () - 3600 * 23)) {
		$token = $this->get_acf_token ();
		/*
		 * $data = array ( "token" => $token, "c_time" => time () ); $acf_model->where ( "id={$acf['id']}" )->save ( $data );
		 */
			/* } else {
				$token = $acf ['token'];
			} */
		/* } */
		if ($token != '' && $token != null) {
			$license_nums = $car ['license_number'];
			$provinceCode = urlencode ( mb_substr ( $license_nums, 0, 1, 'utf-8' ) );
			$carNumber = mb_substr ( $license_nums, 1, strlen ( $license_nums ), 'utf-8' );
			$engineLen = $region ['engine_nums'];
			$frameLen = $region ['frame_nums'];
			$engine_number = substr ( $car ['engine_number'], - $engineLen );
			$frame_number = substr ( $car ['frame_number'], - $frameLen );
			$url = "http://120.26.57.239/api/queryCarViolateInfo?provinceCode=$provinceCode&carNumber=$carNumber&vioCityCode={$region['acode']}&carType=0&carFrame={$frame_number}&carEngine={$engine_number}";
			$log->write ( $url, 'DEBUG', '', dirname ( $_SERVER ['SCRIPT_FILENAME'] ) . '/Logs/Bizapi/' . date ( 'y_m_d' ) . '.log' );
			$ch = curl_init ();
			$header = array (
				"token: $token" 
				);
			curl_setopt ( $ch, CURLOPT_URL, $url );
			curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
			curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, FALSE );
			curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt ( $ch, CURLOPT_HTTPHEADER, $header );
			$output = curl_exec ( $ch );
			curl_close ( $ch );
			$jsoninfo = json_decode ( $output, true );
			$log->write ( "aichefang:" . $output, 'DEBUG', '', dirname ( $_SERVER ['SCRIPT_FILENAME'] ) . '/Logs/Bizapi/' . date ( 'y_m_d' ) . '.log' );
		} else {
			$jsoninfo = array ();
			$jsoninfo ['code'] = 1;
		}
		return $jsoninfo;
	}

	function get_acf_token() {
		$url = "http://120.26.57.239/api/getAccessToken?merKey=" . merKey . "&merCode=" . merCode;
		$ch = curl_init ();
		curl_setopt ( $ch, CURLOPT_URL, $url );
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, FALSE );
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
		$output = curl_exec ( $ch );
		curl_close ( $ch );
		$log = new Log ();
		$log->write ( "get_token:" . $output, 'DEBUG', '', dirname ( $_SERVER ['SCRIPT_FILENAME'] ) . '/Logs/Bizapi/' . date ( 'y_m_d' ) . '.log' );
		$jsoninfo = json_decode ( $output, true );
		$token = $jsoninfo ['data'] [0] ['accessToken'];
		return $token;
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

}