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
	
	public function sendFinalJsonResult($openid, $carHash, $passthruQuery, $handleTime, $json_option=0){
		$data = array (
			'fanhuicode' => 1000,
			'openid' => $openid,
			'cheliangzhiwen' => $carHash,
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
		$openid = $_REQUEST ['openid'];
		$holderName = $_REQUEST ['chezhumingcheng'];
		$holderPhone = $_REQUEST ['chezhudianhua'];
		$holderCity = $_REQUEST ['yonghuchengshi'];		
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
		
		// create the bizapi user
		$user_model = D ( "User" );
		if(!empty($openid)){
			$user = $user_model->where("openid='$openid'")->find();
			if(empty($user)){
				$this->sendJsonResult(3009, $passthruQuery, 0);
			}
			$user_id = $user['id'];
		}
		else{
			$data = array ();
			$data ['group_id'] = 90;
			$username = "BIZAPI_USER_" . microtime(TRUE) . "." . mt_rand(0,1000);
			$data ['username'] = $username;
			$data ['nickname'] = $username;
			$openid = md5($username);
			$data ['openid'] = $openid;
			$data ['bizid'] = $openid;
			$data ['is_att'] = 0;
			$data ['create_time'] = time ();
			$data ['channel'] = self::$channel;
			$data ['channel_key'] = "BIZAPI_" . $bizapi["id"];
			$user_model->add ( $data );
			$user_id = $user_model->getLastInsID ();
		}
		
		// save car if not exist, any channel
		$data = array ();
		$data ['license_number'] = $licRegion . strtoupper ( $licNumber );
		$data ['frame_number'] = strtoupper ( $frameNumber );
		$data ['engine_number'] = strtoupper ( $engineNumber );
		$car_model = M ( "Car" );
		$car = $car_model->where ( $data )->find ();
		$newcar = false;
		$carHash = md5($data ['license_number'] . $data ['frame_number'] . $data ['engine_number']);
		if (empty ( $car )) {
			$data ['hash'] = $carHash;
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
		$this->sendFinalJsonResult($openid, $carHash, $passthruQuery, $_handle);
	}
	
	function tuiding() {
		$log = new Log();
		$log->write(json_encode($_REQUEST));
		// read input
		$carHash = $_REQUEST ['cheliangzhiwen'];
		$openid = $_REQUEST ['openid'];
		$appId = $_REQUEST ['APPID'];
		$appKey = $_REQUEST ['APPKEY'];
		$timestamp = $_REQUEST ['timestamp'];
		$passthruQuery = $_REQUEST ['touchuancanshu'];
		// valid input
		if($carHash == ""){
			$this->sendJsonResult(3007, $passthruQuery, 0);
		}
		if($openid == ""){
			$this->sendJsonResult(3009, $passthruQuery, 0);
		}
		if($timestamp == ""){
			$this->sendJsonResult(2004, $passthruQuery, 0);
		}
		if($appId == "" || $appKey == ""){
			$this->sendJsonResult(2005, $passthruQuery, 0);
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
		
		$user_model = D ( "User" );
		$user = $user_model->where("openid='$openid'")->find();
		if(empty($user)){
			$this->sendJsonResult(3009, $passthruQuery, 0);
		}
		else{
			$user_id = $user['id'];
		}
		
		$data = array ();
		$data ['hash'] = $carHash;
		$car_model = M ( "Car" );
		$car = $car_model->where ( $data )->find ();
		if (empty ( $car )) {
			$this->sendJsonResult(3007, $passthruQuery, 0);
		}
		else{
			$car_id = $car["id"];
		}
		
		// check the bizapi has already subcribe the car, create new user if not.
		$uc_model = M ( "User_car" );
		$data = array (
			"user_id" => $user_id,
			"car_id" => $car_id
			);
		$uc = $uc_model->where ( $data )->find ();
		if(empty ( $uc )) {
			$this->sendJsonResult(3008, $passthruQuery, 0);
		}
		else{
			$data = array (
				"is_sub" => 1,
				"c_time" => $now
				);
			$uc_model->where ( "id={$uc['id']}" )->save($data);
		}
		
		// end transaction
		$_end = microtime(TRUE);
		$_handle = number_format($_end - $_start, 4);
		
		$data = array (
			"last_time" => time()
			);
		$bizapi_model -> where(" id = {$bizapi['id']}") -> save($data);
		
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
				"license_number" => $licRegion . $licNumber,
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
	
	function addQueryLog($appId, $carHash, $condition, $code){
		$model = M("bizapi_query");
		$data = array (
			"channel" => $appId,
			"c_time" => time(),
			"car_hash" => $carHash,
			"condition" => $condition,
			"result" => $code
			);
		$model->add($data);
	}
	
	function sendQueryResult($appId, $carHash, $condition, $code, $passthruQuery, $handleTime, $json_option = 0){
		$this->addQueryLog($appId, $carHash, $condition, $code);
		$this->sendJsonResult($code, $passthruQuery, $handleTime, $json_option);
	}
	
	function weizhangQuery() {
		$log = new Log();
		$log->write(json_encode($_REQUEST));
		// read input
		$carHash = $_REQUEST ['cheliangzhiwen'];
		$condition = $_REQUEST ['chulizhuangtai'];
		if($condition == ""){
			$condition = "TOTAL_UNPAY";
		}
		$appId = $_REQUEST ['APPID'];
		$appKey = $_REQUEST ['APPKEY'];
		$timestamp = $_REQUEST ['timestamp'];
		//$sign = $_REQUEST ['qianming'];
		$passthruQuery = $_REQUEST ['touchuancanshu'];
		// valid input
		if($carHash == ""){
			$this->sendQueryResult($appId, $carHash, $condition, 3007, $passthruQuery, 0);
		}
		$data = array ();
		$data ['hash'] = $carHash;
		$car_model = M ( "Car" );
		$car = $car_model->where ( $data )->find ();
		if (empty ( $car )) {
			$this->sendQueryResult($appId, $carHash, $condition, 3007, $passthruQuery, 0);
		}
		
		if($timestamp == ""){
			$this->sendQueryResult($appId, $carHash, $condition, 2004, $passthruQuery, 0);
		}
		if($appId == "" || $appKey == ""){
			$this->sendQueryResult($appId, $carHash, $condition, 2005, $passthruQuery, 0);
		}
		
		$bizapi_model = M ( "bizapi" );
		$now = time();
		$bizapi = $bizapi_model -> where(" app_id = '$appId' and state = 1 and expiration_time >= $now ") ->find();
		if(empty($bizapi)){
			$this->sendQueryResult($appId, $carHash, $condition, 2001, $passthruQuery, 0);
		}
		else{
			if($bizapi['app_key'] != $appKey){
				$this->sendQueryResult($appId, $carHash, $condition, 2002, $passthruQuery, 0);
			}
		}
		// start transaction
		$_start = microtime(TRUE);
		
		$pay_type = 1;
		$with_year = false;
		if($condition == "TOTAL_UNPAY"){
			$pay_type = 1;
			$with_year = false;
		}
		if($condition == "TOTAL_PAID"){
			$pay_type = 2;
			$with_year = false;
		}
		if($condition == "TOTAL_ALL"){
			$pay_type = 0;
			$with_year = false;
		}
		if($condition == "THISYEAR_UNPAY"){
			$pay_type = 1;
			$with_year = true;
		}
		if($condition == "THISYEAR_PAID"){
			$pay_type = 2;
			$with_year = true;
		}
		if($condition == "TOTAL_ALL"){
			$pay_type = 0;
			$with_year = true;
		}
		
		$where = "license_number = '{$car['license_number']}'";

		if($pay_type == 1){
			$where .= " and is_manage = 0"; 
		}
		if($pay_type == 2){
			$where .= " and is_manage = 2"; 
		}
		if($with_year){
			$year = date("Y");
			$thisyear = strtotime($year . "0101");
			$where .= " and time >= $thisyear"; 
		}
		
		$endorsement_model = M ( "Endorsement" );
		$end = $endorsement_model->where($where)->select();
		$end_list = array();
		foreach ( $end as $k => $v ) {
			$end_data = array();
			$end_data["weizhangzhiwen"] = $v["hash"];
			$end_data["chepaihao"] = urlencode($v["license_number"]);
			$end_data["weizhangshijian"] = $v["time"];
			$end_data["weizhangchengshi"] = urlencode($v["area"]);
			$end_data["weizhangdaima"] = $v["code"];
			$end_data["weizhangfajin"] = $v["money"];
			$end_data["weizhangfafen"] = $v["points"];
			$end_data["weizhangdidian"] = urlencode($v["address"]);
			$end_data["weizhangshuoming"] = urlencode($v["content"]);
			$manage_state = "未处理";
			$daibanlink = "";
			if($v["is_manage"] == 0){
				$fuwu = $this->find_fuwu($car["id"], $v['code'], $v['money'], $v['points'], $v['area']);
				if(!empty($fuwu)){
					$wxUrl = "";
					if(runEnv == 'production'){
						$wxUrl = "http://weixin.xiaoxianlink.com";
					}
					elseif(runEnv == 'test'){
						$wxUrl = "http://wxdev.xiaoxianlink.com";
					}
					else{
						$wxUrl = "http://wx.xiaoxian.com";
					}
					$daibanlink = $wxUrl . "/index.php?g=weixin&m=scan&a=scan_info&id=".$v['id']."&car_id=".$car["id"]."&license_number=". urlencode($v ['license_number'] ) ."&so_id=".$fuwu['so_id']."&so_type=".$fuwu['so_type']."&user_id=";
				}
			}
			if($v["is_manage"] == 1){
				$manage_state = "处理中";
			}
			if($v["is_manage"] == 2){
				$manage_state = "已处理";
			}
			$end_data["shifouchuli"] = urlencode($manage_state);
			$end_data["daibanlink_wexin"] = $daibanlink;
			$end_list[] = $end_data;
		}
		
		$scan_state = "正常扫描";
		if($car["scan_state"] == 0){
			$scan_state = "停止扫描: " . $car["scan_state_desc"];
		}
		
		// end transaction
		$_end = microtime(TRUE);
		$_handle = number_format($_end - $_start, 4);
		
		$data = array (
			"last_time" => time()
			);
		$bizapi_model -> where(" id = {$bizapi['id']}") -> save($data);
		
		$this->addQueryLog($appId, $carHash, $condition, 4000);
		
		$data = array (
			'fanhuicode' => 4000,
			"cheliangzhiwen" => $car["hash"],
			"shaomiaoshuoming" => urlencode($scan_state),
			"weizhangliebiao" => $end_list,
			"timestamp" => time (),
			'handletime' => $_handle,
			"touchuangstring" => ($passthruQuery)? $passthruQuery :""
			);
			
		header('Content-Type:application/json; charset=utf-8');
        exit(json_encode($data, $json_option));
	}
	
	function orderQuery() {
		$log = new Log();
		$log->write(json_encode($_REQUEST));
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
		$_result = $_model->query("select e.* from cw_endorsement e where e.id = {$order['endorsement_id']}");
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
	
	function push_weizhang(){
		$car_id = $_REQUEST ['car_id'];
		$end_id = $_REQUEST ['end_id'];
		$this->push($car_id, $end_id);
	}
	// 推送
	function push($car_id, $end_id) {
		$log = new Log ();
		$log->write ( "send---------------------------$end_id", 'DEBUG', '', dirname ( $_SERVER ['SCRIPT_FILENAME'] ) . '/Logs/Bizapi/' . date ( 'y_m_d' ) . '.log' );
		$end_model = M ( "endorsement" );
		$end_info = $end_model->where ( "id='$end_id'" )->find ();
		if (empty ( $end_info )) {
			return false;
		}
		$log->write ( "senddata---------------------------" . json_encode ( $end_info ), 'DEBUG', '', dirname ( $_SERVER ['SCRIPT_FILENAME'] ) . '/Logs/Bizapi/' . date ( 'y_m_d' ) . '.log' );
		$now = time();
		$car_model = M ( "Car" );
		$car_info = $car_model->where ( "id='$car_id'" )->find ();
		$user_model = M ();
		$user = $user_model->table ( "cw_user as u" )->join ( "cw_user_car as uc on uc.user_id = u.id" )->field ( "u.id, u.openid, u.nickname, u.channel, u.channel_key" )->where ( "uc.car_id='$car_id' and uc.is_sub = 0" )->select ();
		$date = date ( "Y年m月d日 H:i", $end_info ['time'] );
		foreach ( $user as $p ) {
			if($p['channel'] == 99){
				$bizapi_id = substr($p['channel_key'], 7);
				$bizapi_model = M('bizapi');
				$bizapi = $bizapi_model->where("id = $bizapi_id and state = 1 and expiration_time >= $now ")->find();
				if(!empty($bizapi)){
					$target_url = $bizapi['app_domain'];
					if(false === strpos($target_url, 'http://')){
						$target_url = "http://" . $target_url;
					}
					$target_url = $target_url . "/api/weizhang/weizhangtixing";
					$fuwu = $this->find_fuwu($car_id, $end_info['code'], $end_info['money'], $end_info['points'], $end_info['area']);
					$post_data = array (
						'chepai' => $car_info ['license_number'],
						'weizhangtime' => $end_info['time'],
						'weizhangcity' => $end_info['area'],
						'weizhangcode' => $end_info['code'],
						'fajin' => $end_info['money'],
						'fafen' => $end_info['points'],
						'zhangshucode' => $end_info['certificate_code'],
						'weizhangaddress' => $end_info['address'],
						'weizhanginfo' => $end_info['content'],
						'weizhangoffice' => $end_info['office'],
						'ischuli' => 'N',
						'timestamp' => time()
					);
					if(!empty($fuwu)){
						$post_data['ischuli'] = 'Y';
						$post_data['daibanprice'] = $fuwu['so_money'];
						$wxUrl = "";
						if(runEnv == 'production'){
							$wxUrl = "http://weixin.xiaoxianlink.com";
						}
						elseif(runEnv == 'test'){
							$wxUrl = "http://wxdev.xiaoxianlink.com";
						}
						else{
							$wxUrl = "http://wx.xiaoxian.com";
						}
						$post_data['daibanlink'] = $wxUrl . "/index.php?g=weixin&m=scan&a=scan_info&id=".$end_info['id']."&car_id=".$car_id."&license_number=". urlencode($car_info ['license_number'] ) ."&so_id=".$fuwu['so_id']."&so_type=".$fuwu['so_type']."&user_id=".$p['id'];
					}
					$log->write ( "target_url= " . $target_url, 'DEBUG', '', dirname ( $_SERVER ['SCRIPT_FILENAME'] ) . '/Logs/Bizapi/' . date ( 'y_m_d' ) . '.log' );
					$log->write ( serialize ( http_build_query($post_data) ), 'DEBUG', '', dirname ( $_SERVER ['SCRIPT_FILENAME'] ) . '/Logs/Bizapi/' . date ( 'y_m_d' ) . '.log' );
					$dataRes = $this->request_post($target_url, http_build_query($post_data));
					$log->write ( serialize ( $dataRes ), 'DEBUG', '', dirname ( $_SERVER ['SCRIPT_FILENAME'] ) . '/Logs/Bizapi/' . date ( 'y_m_d' ) . '.log' );
					
					$data = array (
						"from_userid" => 0,
						"openid" => $p ['openid'],
						"tar_id" => $end_info ['id'],
						"create_time" => time (),
						"msg_type" => 2,
						"nums" => 1,
						"all_points" => $end_info ['points'],
						"all_money" => $end_info ['money'] 
					);
					$model = M ( "Message" );
					$model->add ( $data );
				}
			}
		}
	}
	
	function find_fuwu($car_id, $code, $money, $points, $area, $exclude_list = null){
		$log = new Log ();
		$fuwu = Array();
		$region_model = M ( "Region" );
		$where = array (
				"city" => $area,
				"level" => 2,
				"is_dredge" => 0 
		);
		$region = $region_model->where ( $where )->order ( 'id' )->find ();
		if (empty ( $region )) {
			$city_id1 = 0;
		}
		else{
			$city_id1 = $region ['id'];
		}
		
		$where = array (
				"id" => $car_id
		);
		$car_model = M ( "Car" );
		$car = $car_model->where ( $where )->find ();
		
		$a_class = array("京", "沪", "津", "渝");
		$l_nums = "";
		$l_nums_a = mb_substr ( $car ['license_number'], 0, 1, 'utf-8' );
		if(in_array($l_nums_a, $a_class)){
			$l_nums = $l_nums_a;
		}
		else{
			$l_nums = mb_substr ( $car ['license_number'], 0, 2, 'utf-8' );
		}
		$region_model = M ( "Region" );
		$region = $region_model->where ( "nums = '$l_nums'" )->find ();
		$region = $region_model->where ( "city = '{$region['city']}'" )->order ( "id" )->find ();
		if (empty ( $region )) {
			$city_id2 = 0;
		} else {
			$city_id2 = $region ['id'];
		}
		
		$violation_model = M("violation");
		$violation = $violation_model->field("money, points")->where("code = '$code'")->find();
		if(empty($violation) || $violation['state'] == 1){
			return $fuwu;
		}
		
		$where = "";
		if(!empty($exclude_list)){
			$where = "srv.id not in (" . implode(",", $exclude_list) . ") and ";
		}
		$s_code = substr($code, 0, 4);
		
		$so_model = M(''); // 1.a
		$so_sql = "select srv.id as services_id, so.id as so_id, so.money from cw_services as srv, cw_services_city as scity, cw_services_code as scode, cw_services_order as so where $where srv.id = scity.services_id and srv.id = scode.services_id and srv.id = so.services_id and srv.state = 0 and srv.grade > 4 and ((scity.code = $city_id1 and scity.state = 0) or (scity.code = $city_id2 and scity.state = 0)) and ((scode.code = '$code' or scode.code = '$s_code') and scode.state = 0 ) and so.violation = '$code' and (so.code = $city_id1 or so.code = $city_id2) group by srv.id order by money asc ";
		//$log->write ( $so_sql );
		$solist = $so_model->query($so_sql);
		
		$sd_model = M(''); // 1.b
		$sd_sql = "select * from (select dyna.services_id, dyna.id as so_id, ($money + dyna.fee + dyna.point_fee * $points) dyna_fee from cw_services as srv, cw_services_city as scity, cw_services_code as scode, cw_services_dyna as dyna where  $where srv.id = scity.services_id and srv.id = scode.services_id and srv.id = dyna.services_id and srv.state = 0 and srv.grade > 4 and ((scity.code = $city_id1 and scity.state = 0) or (scity.code = $city_id2 and scity.state = 0)) and (scode.code = '$code' or scode.code = '$s_code') and scode.state = 0 and (dyna.code = $city_id1 or dyna.code = $city_id2) ORDER BY dyna_fee ASC) as service_dyna group by services_id order by dyna_fee asc";
		//$log->write ( $sd_sql );
		$sdlist = $sd_model->query($sd_sql);
		
		// we now get the lowest price
		$lowest_price = -1;
		$so_id = -1;
		$so_type = -1;
		if( ! empty($solist)){
			$lowest_price = $solist[0]['money'];
			$so_id = $solist[0]['so_id'];
			$so_type = 1;
		}
		if( ! empty($sdlist)){
			if($lowest_price > -1 ){
				if($lowest_price > $sdlist[0]['dyna_fee']){
					$lowest_price = $sdlist[0]['dyna_fee'];
					$so_id = $sdlist[0]['so_id'];
					$so_type = 2;
				}
			}
			else{
				$lowest_price = $sdlist[0]['dyna_fee'];
				$so_id = $sdlist[0]['so_id'];
				$so_type = 2;
			}
		}
		//$log->write ( "lowest_price=". $lowest_price );
		if($lowest_price == -1){
			return $fuwu;
		}
		
		$where = "";
		$firstCondition = false;
		$services_id_by_money = array ();
		if( ! empty($solist)){
			foreach ( $solist as $p => $c ) {
				if($c['money'] == $lowest_price){
					if ($firstCondition == false) {
						$where .= " services_id = {$c['services_id']}";
						$firstCondition = true;
					} else {
						$where .= " or services_id = {$c['services_id']}";
					}
					$services_id_by_money[] = $c['services_id'];
				}
				else{
					break;
				}
			}
		}
		if( ! empty($sdlist)){
			foreach ( $sdlist as $p => $c ) {
				if($c['dyna_fee'] == $lowest_price){
					if ($firstCondition == false) {
						$where .= " services_id = '{$c['services_id']}'";
						$firstCondition = true;
					} else {
						$where .= " or services_id = '{$c['services_id']}'";
					}
					$services_id_by_money[] = $c['services_id'];
				}
				else{
					break;
				}
			}
		}
		$order_model = M(''); // 2
		$sql = "SELECT COUNT(*) as nums, `services_id` FROM `cw_order` WHERE $where GROUP BY `services_id` ORDER BY nums";
		//$log->write ( $sql);
		$orderlist = $order_model->query ( $sql );
		$services_id_by_ordernum = array ();
		foreach ( $orderlist as $p => $c ) {
			$services_id_by_ordernum [] = $c ['services_id'];
		}
		$services = array_diff ( $services_id_by_money, $services_id_by_ordernum );
		if (! empty ( $services )) {
			foreach ( $services as $r ) {
				$services_id = $r;
				break;
			}
		} else {
			$services_id = $orderlist [0] ['services_id'];
		}
		//$log->write ( "services_id=". $services_id );
		// 3
		$fuwu['s_id'] = $services_id;
		$fuwu['so_id'] = $so_id;
		$fuwu['so_type'] = $so_type;
		$fuwu['so_money'] = $lowest_price;
		
		return $fuwu;
	}
	
	function push_order(){
		$end_id = $_REQUEST['end_id'];
		$this->__push_order($end_id);
	}
	
	// 推送
	function __push_order($end_id) {
		$log = new Log();
		$model = M ();
		$r = $model->table ( "cw_order as o" )->join ( "cw_user as u on u.id=o.user_id" )->field ( "u.channel, u.channel_key")->where ( "o.endorsement_id = '$end_id'" )->find ();
		if (! empty ( $r )) {
			if($r["channel"] == 99){
				$_model = M("");
				$_result = $_model->query("select e.* from cw_endorsement e where e.id = $end_id");
				$result = $_result[0];
				
				$bizapi_id = substr($r['channel_key'], 7);
				$bizapi_model = M('bizapi');
				$now = time();
				$bizapi = $bizapi_model->where("id = $bizapi_id and state = 1 and expiration_time >= $now ")->find();
				
				if(!empty($bizapi)){
					$target_url = $bizapi['app_domain'];
					if(false === strpos($target_url, 'http://')){
							$target_url = "http://" . $target_url;
						}
					$target_url = $target_url . "/api/weizhang/banlijieguo";
					if($state == 1){
						$state_desc = "未处理";
					}
					if($state == 3){
						$state_desc = "处理中";
					}
					if($state == 4){
						$state_desc = "已处理";
					}
					$post_data = array (
						'chepai' => $result['license_number'],
						'weizhangtime' => $result['time'],
						'weizhangcity' => $result['area'],
						'weizhangcode' => $result['code'],
						'fajin' => $result['money'],
						'fafen' => $result['points'],
						'dingdanhao' => $order['order_sn'],
						'banlizhuangtai' => $state_desc,
						'timestamp' => time()
						);
					$log->write ( "target_url=" . $target_url, 'DEBUG', '', dirname ( $_SERVER ['SCRIPT_FILENAME'] ) . '/Logs/Ziniu/' . date ( 'y_m_d' ) . '.log' );
					$log->write ( serialize ( http_build_query($post_data) ), 'DEBUG', '', dirname ( $_SERVER ['SCRIPT_FILENAME'] ) . '/Logs/Ziniu/' . date ( 'y_m_d' ) . '.log' );
					$dataRes = $this->request_post($target_url, http_build_query($post_data));
					$log->write ( serialize ( $dataRes ), 'DEBUG', '', dirname ( $_SERVER ['SCRIPT_FILENAME'] ) . '/Logs/Ziniu/' . date ( 'y_m_d' ) . '.log' );
				}
			}
		}
	}
}