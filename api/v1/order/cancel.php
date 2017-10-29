<?php
require "../configuration.php";
$api_key = $_SERVER['HTTP_API_KEY'];
$api_secret = $_SERVER['HTTP_API_SECRET'];
$order_id = $_POST['order_id'];

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
if(! isset($order_id)){
	$data['message'] = 'An order_id must be provided';
	exit_program();
}

$sql = "SELECT * FROM `Orderbook` WHERE `order_id` = :order_id AND `username`=:username";
$keys = array(":order_id" => $order_id, ":username" => $user_database['username']);
$order = sql_get($sql, $keys);
if(count($order) > 0){
	//Delete Order
	$sql = "DELETE FROM `Orderbook` WHERE `order_id` = :order_id";
	$keys = array(":order_id" => $order_id);
	sql_post($sql, $keys);
	
	//Update Order Table
	$sql = "UPDATE `Orders` SET `status` = 'cancelled', `remaining_quantity` = 0, `terminated_time` = :time WHERE `order_id` = :order_id";
	$keys = array(":time" => time(), ":order_id" => $order_id);
	sql_post($sql, $keys);
	
	//Update Liquidation Price
	update_liquidation_price($user_database['username']);
	
	//Return
	$data['message'] = 'Order ' . $order_id . ' canceled.';
	$data['canceled'] = true;
	$data['result'] = 'success';
	$data['order_id'] = $order_id;
	exit_program();
} else {
	//Order Not Found
	$data['message'] = 'Order ' . $order_id . ' not found.';
	$data['canceled'] = false;
	$data['result'] = 'failure';
	$data['order_id'] = $order_id;
	exit_program();
}
?>