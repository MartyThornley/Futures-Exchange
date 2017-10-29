<?php
require "../configuration.php";
$api_key = $_SERVER['HTTP_API_KEY'];
$api_secret = $_SERVER['HTTP_API_SECRET'];

$response = [];

if(! isset($api_key)){
	$data['message'] = 'An API key must be provided';
	exit_program();
}
if(! isset($api_secret)){
	$data['message'] = 'An API secret must be provided';
	exit_program();
}

$user_database = authenticate_get_user($api_key, $api_secret);
if($user_database == false){
	$data['message'] = 'Authentication Failed. Check to make sure your API Key and API Secret are correct.';
	exit_program();
}
api_limit_user($user_database);

print(json_encode(get_available_balance($user_database)));
?>