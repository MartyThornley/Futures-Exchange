<?php
require "../configuration.php";
$api_key = $_SERVER['HTTP_API_KEY'];
$api_secret = $_SERVER['HTTP_API_SECRET'];

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
if(isset($_POST['engine']) and $_POST['engine'] == 'true'){
	$engine = true;
} else {
	api_limit_user($user_database);
}

$sql = "SELECT * FROM `Orderbook` WHERE `username`=:username";
$keys = array(":username" => $user_database['username']);
$orders = sql_get($sql, $keys);

if(count($orders) > 0){
	//Delete All Orders
	$sql = "DELETE FROM `Orderbook` WHERE `username` = :username";
	$keys = array(":username" => $user_database['username']);
	sql_post($sql, $keys);
	
	//Update Order Table
	$sql = "UPDATE `Orders` SET `status` = 'cancelled', `remaining_quantity` = 0, `terminated_time` = :time WHERE `username` = :username AND `status` = 'accepted'";
	$keys = array(":time" => time(), ":username" => $user_database['username']);
	sql_post($sql, $keys);
	
	//Update Liquidation Price
	update_liquidation_price($user_database['username']);
	
	//Return
	$data['message'] = 'All orders cancelled.';
	$data['canceled'] = true;
	$data['result'] = 'success';
	exit_program();
} else {
	//No Orders
	$data['message'] = 'No orders can be cancelled.';
	$data['canceled'] = false;
	$data['result'] = 'failure';
	exit_program();
}
?>