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

$mark_price = get_fair_value()['mark'];
if(intval($user_database['margin_position']) < 0){
	$position_side = 'sell';
} elseif(intval($user_database['margin_position']) > 0){
	$position_side = 'buy';
} else {
	print(json_encode(array()));
	exit;
}
$position_amount = abs(intval($user_database['margin_position']));
$position_price = abs(round(intval($user_database['margin_position']) / floatval($user_database['margin_bitcoin']), 2));
$position_unrealized_pl = -(floatval($user_database['margin_bitcoin']) + (intval($user_database['margin_position']) / $mark_price));

$position_array = array();
$position_array['side'] = $position_side;
$position_array['amount'] = abs($position_amount);
$position_array['price'] = round(abs($position_price), 2);
$position_array['unrealized_bitcoin_pl'] = round($position_unrealized_pl, 8);
$position_array['liquidation_price'] = floatval($user_database['liquidation_price']);
print(json_encode($position_array));
?>