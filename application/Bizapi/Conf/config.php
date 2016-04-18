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
