<?php
if($_SERVER ['SERVER_NAME'] == "bizapi.xiaoxianlink.com"){
	define ( 'runEnv', "production" );
}
elseif($_SERVER ['SERVER_NAME'] == "testapi.xiaoxianlink.com"){
	define ( 'runEnv', "test" );
}
else{
	define ( 'runEnv', "dev" );
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

$action = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = $_POST['action'];
	$appId = $_POST['app_id'];
	$appKey = $_POST['app_key'];
	
	if($action == "cheliangdingyue"){
		$openId = $_POST['open_id'];
		$carNumber = $_POST['car_number'];
		$licRegion = mb_substr($carNumber, 0, 1, "utf-8");
		$licNumber = mb_substr($carNumber, 3, 6);
		$frameNumber = $_POST['car_frame_number'];
		$engineNumber = $_POST['car_engine_number'];
		$timestamp = time();
		$sign = md5($appId . $carNumber . $frameNumber . $engineNumber . $timestamp . $appKey);
		$passthruQuery = "apidemo";

		$post_data = array (
				"chepaitou" => urlencode($licRegion),
				"chepaihao" => $licNumber,
				"chejiahao" => $frameNumber,
				"fadongjihao" => $engineNumber,
				"openid" => $openId,
				"APPID" => $appId,
				"APPKEY" => $appKey,
				"timestamp" => $timestamp,
				"qianming" => $sign,
				"touchuanstring" => $passthruQuery
			);
	}
	if($action == "weizhangtuiding"){
		$openId = $_POST['open_id'];
		$cheliangzhiwen = $_POST['cheliangzhiwen'];
		$timestamp = time();
		$passthruQuery = "apidemo";

		$post_data = array (
				"cheliangzhiwen" => $cheliangzhiwen,
				"openid" => $openId,
				"APPID" => $appId,
				"APPKEY" => $appKey,
				"timestamp" => $timestamp,
				"touchuancanshu" => $passthruQuery
			);
	}
	
	if($action == "weizhangchaxun"){
		$cheliangzhiwen = $_POST['cheliangzhiwen'];
		$timestamp = time();
		$passthruQuery = "apidemo";

		$post_data = array (
				"cheliangzhiwen" => $cheliangzhiwen,
				"APPID" => $appId,
				"APPKEY" => $appKey,
				"timestamp" => $timestamp,
				"touchuancanshu" => $passthruQuery
			);
	}
	
	$target_url = "";
	if(runEnv == 'production'){
		$target_url = "http://bizapi.xiaoxianlink.com";
	}
	elseif(runEnv == 'test'){
		$target_url = "http://testapi.xiaoxianlink.com";
	}
	else{
		$target_url = "http://ba.xiaoxian.com";
	}
	$target_url = $target_url . "/api/weizhang/" . $action;
	$result = request_post($target_url, $post_data);
}
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>bizapi test</title>
	<link rel="stylesheet" href="weui.css"/>
	<script src="jquery.js"></script>
	
<head>
<body>
	<div class="bd" style="height: 100%;">
		<div class="weui_tab">
			<div id="navbar" class="weui_navbar">
				<div id="nb_dy" class="weui_navbar_item weui_bar_item_on">订阅</div>
				<div id="nb_td" class="weui_navbar_item">退订</div>
				<div id="nb_cx" class="weui_navbar_item">违章查询</div>
			</div>
			<div class="weui_tab_bd">
				<div id="dy_form">
					<form id="form" method="post">
						<input type="hidden" name="action" value="cheliangdingyue"/>
						<div>
							<span>车牌号：</span>
							<input type="text" id="car_number" name="car_number" value="<?php if(isset($carNumber)) echo $carNumber ?>">
						</div>
						<div>
							<span>车架号：</span>
							<input type="text" id="car_frame_number" name="car_frame_number" value="<?php if(isset($frameNumber)) echo $frameNumber ?>">
						</div>
						<div>
							<span>发动机号：</span>
							<input type="text" id="car_engine_number" name="car_engine_number" value="<?php if(isset($engineNumber)) echo $engineNumber ?>">
						</div>
						<div>
							<span>open id：</span>
							<input type="text" id="open_id" name="open_id" value="<?php if(isset($openId)) echo $openId ?>">
						</div>
						<div>
							<span>bizapi app_id：</span>
							<input type="text" id="app_id" name="app_id" value="<?php if(isset($appId)) echo $appId ?>">
						</div>
						<div>
							<span>bizapi app_key：</span>
							<input type="text" id="app_key" name="app_key" value="<?php if(isset($appKey)) echo $appKey ?>">
						</div>
						<div>
							<input type="submit" id="submit" name="submit" value="submit" >
						</div>
					</form>
				</div>
				<div id="td_form" style="display:none">
					<form id="form" method="post">
						<input type="hidden" name="action" value="weizhangtuiding"/>
						<div>
							<span>车辆指纹：</span>
							<input type="text" id="cheliangzhiwen" name="cheliangzhiwen" value="<?php if(isset($cheliangzhiwen)) echo $cheliangzhiwen ?>">
						</div>
						<div>
							<span>open id：</span>
							<input type="text" id="open_id" name="open_id" value="<?php if(isset($openId)) echo $openId ?>">
						</div>
						<div>
							<span>bizapi app_id：</span>
							<input type="text" id="app_id" name="app_id" value="<?php if(isset($appId)) echo $appId ?>">
						</div>
						<div>
							<span>bizapi app_key：</span>
							<input type="text" id="app_key" name="app_key" value="<?php if(isset($appKey)) echo $appKey ?>">
						</div>
						<div>
							<input type="submit" id="submit" name="submit" value="submit" >
						</div>
					</form>
				</div>
				<div id="wz_form" style="display:none">
					<form id="form" method="post">
						<input type="hidden" name="action" value="weizhangchaxun"/>
						<div>
							<span>车辆指纹：</span>
							<input type="text" id="cheliangzhiwen" name="cheliangzhiwen" value="<?php if(isset($cheliangzhiwen)) echo $cheliangzhiwen ?>">
						</div>
						<div>
							<span>bizapi app_id：</span>
							<input type="text" id="app_id" name="app_id" value="<?php if(isset($appId)) echo $appId ?>">
						</div>
						<div>
							<span>bizapi app_key：</span>
							<input type="text" id="app_key" name="app_key" value="<?php if(isset($appKey)) echo $appKey ?>">
						</div>
						<div>
							<input type="submit" id="submit" name="submit" value="submit" >
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
	
	<?php 
		if(runEnv == "dev"){
	?>
	<div>
		<a href="/Logs/Bizapi/" target="blank">Bizapi Logs</a>&nbsp;&nbsp;
		
		<a href="http://zn.xiaoxian.com/Logs/Weizhang/" target="blank">Weizhang Logs</a>
		
	</div>
	<?php
	}
	?>
	<div>
		<p>
		<?php if(isset($target_url)) echo $target_url ?>
		</p>
		<p>
		<?php if(isset($result)) echo $result ?>
		</p>
	</div>
	<script>
		$("#navbar").on('click', '.weui_navbar_item', function () {
			if($(this).html() == "订阅"){
				$("#dy_form").show();
				$("#td_form").hide();
				$("#wz_form").hide();
			}
			if($(this).html() == "退订"){
				$("#dy_form").hide();
				$("#td_form").show();
				$("#wz_form").hide();
			}
			if($(this).html() == "违章查询"){
				$("#dy_form").hide();
				$("#td_form").hide();
				$("#wz_form").show();
			}
			$(this).addClass('weui_bar_item_on').siblings('.weui_bar_item_on').removeClass('weui_bar_item_on');
		});
		<?php
			if($action == "cheliangdingyue"){
		?>
				$("#nb_dy").trigger('click');
		<?php
			}
			elseif($action == "weizhangtuiding"){
		?>
				$("#nb_td").trigger('click');
		<?php
			}
			elseif($action == "weizhangchaxun"){
		?>
				$("#nb_cx").trigger('click');
		<?php
			}
			else{
		?>
				$("#nb_dy").trigger('click');
		<?php
			}
		?>
	</script>
</body>
</html>