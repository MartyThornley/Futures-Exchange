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

if(isset($_POST['start'])){
	$start = intval($_POST['start']);
} else {
	$start = 0;
}

if($start < 0){
	$data['message'] = 'The start parameter must be greater than 0.';
	exit_program();
}

$sql = "SELECT `type`, `amount`, `price`, `bitcoin_amount`, `trade_timestamp`, `trade_id`, `fee` FROM `Trades` WHERE `username` = :username ORDER BY `trade_timestamp` DESC LIMIT 50 OFFSET :start";
$query = $conn->prepare($sql);
$query->bindParam(':username', $user_database['username']);
$query->bindParam(':start', $start, PDO::PARAM_INT);
$query->execute();
$trades = $query->fetchAll(PDO::FETCH_ASSOC);

for($i = 0; $i < count($trades); $i++){
	$trades[$i]['side'] = $trades[$i]['type'];
	unset($trades[$i]['type']);
	if(floatval($trades[$i]['fee']) > 0){
		$trades[$i]['type'] = 'market';
	} else {
		$trades[$i]['type'] = 'limit';
	}
	$trades[$i]['amount'] = intval($trades[$i]['amount']);
	$trades[$i]['price'] = round(floatval($trades[$i]['price']), 2);
	$trades[$i]['fee'] = floatval($trades[$i]['fee']);
	$trades[$i]['trade_timestamp'] = intval($trades[$i]['trade_timestamp']);
	$trades[$i]['value'] = abs(round(floatval($trades[$i]['bitcoin_amount']), 8));
	unset($trades[$i]['bitcoin_amount']);
}
print(json_encode($trades));
?>