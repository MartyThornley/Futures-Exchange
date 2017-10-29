<?php
require "../configuration.php";

$username = $_POST['username'];
$password = $_POST['password'];
$email = $_POST['email'];

if(! isset($username)){
	$data['message'] = 'You must provide a username';
	exit_program();
}
if(! isset($password)){
	$data['message'] = 'You must provide a password';
	exit_program();
}
if(! isset($email)){
	$data['message'] = 'You must provide an email';
	exit_program();
}
if(strlen($username) < 6){
	$data['message'] = 'Username must be at least 6 letters';
	exit_program();
}
if(strlen($password) < 6){
	$data['message'] = 'Password must be at least 6 letters';
	exit_program();
}

$user_database = get_user($username);
if(count($user_database) > 0){
	$data['message'] = 'Someone has already taken that username. Please try a new one';
	exit_program();
}

$password_hash = hash('sha256', $password);
$api_key = generateRandomString(32);
$api_secret = generateRandomString(32);
$web_api_key = generateRandomString(32);
$web_api_secret = generateRandomString(32);
$bitcoin_address = "soon";

$system = get_system();
$sql = "INSERT INTO `Users`(`balance`, `margin_bitcoin`, `margin_position`, `api_key`, `api_secret`, `web_api_key`, `web_api_secret`, `api_used`, `api_limit`, `username`, `password_hash`, `email`, `trade_volume`, `deposits`, `withdrawals`, `fees_paid`, `bitcoin_address`, `maker_fee`, `taker_fee`, `locked`, `initial`, `maintenance`) VALUES (0,0,0,:api_key, :api_secret, :web_api_key, :web_api_secret, 0, :api_limit, :username, :password_hash, :email,0,0,0,0,:bitcoin_address, :maker_fee, :taker_fee, :locked, :inital, :maintenance)";
$keys = array(":api_key" => $api_key, ":api_secret" => $api_secret, ":web_api_key" => $web_api_key, ":web_api_secret" => $web_api_secret, ":api_limit" => $system['api_limit'], ":username" => $username, ":password_hash" => $password_hash, ":email" => $email, ":bitcoin_address" => $bitcoin_address, ":maker_fee" => $system['maker_fee'], ":taker_fee" => $system['taker_fee'], ":locked" => 'false', ":inital" => $system['initial'], ":maintenance" => $system['maintenance']);
sql_post($sql, $keys);
$data['message'] = 'You have been successfully registered. Expect a confirmation email at ' . $email . ' soon';
$data['result'] = 'success';
exit_program();
?>