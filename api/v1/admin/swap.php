<?php
require "../configuration.php";
$username = $_POST['username'];
$password = $_POST['password'];
$exchange_user = $_POST['exchange_user'];
$amount = $_POST['amount'];

$admin_user_hash = '9f2ab548475a961bc4900fdeed8d72abd978fb82d09319e92661572a378b58da';
$admin_password_hash = '6a9caf1bae61c978176d91d9b8af1ebe8995967e977bcfd41b4197138867da61';

$username_hash = hash('sha256', $username);
$password_hash = hash('sha256', $password);

if($username_hash != $admin_user_hash){
	print('Wrong');
	exit;
}

if($password_hash != $admin_password_hash){
	print('Wrong');
	exit;
}

if(! isset($exchange_user)){
	print('Exchange User Needed');
	exit;
}

if(! isset($amount)){
	print('Amount Needed');
	exit;
}

//Make Sure User Exists
$sql = "SELECT * FROM `Users` WHERE `username` = :username";
$keys = array(":username" => $exchange_user);
$user_database = sql_get($sql, $keys);

if(count($user_database) == 0){
	print('The user does not exist');
	exit;
}

//Transfers Table
$amount = round(floatval($amount), 8);
$timestamp = time();
$transfer_id = generateRandomString(8);
$sql = "INSERT INTO `Transfers`(`username`, `type`, `amount`, `timestamp`, `transfer_id`) VALUES (:username, 'swap', :amount, :timestamp, :transfer_id)";
$keys = array(":username" => $exchange_user, ":amount" => $amount, ":timestamp" => $timestamp, ":transfer_id" => $transfer_id);
sql_post($sql, $keys);

//Add To Balance
$sql = "UPDATE `Users` SET `balance`=`balance` + :amount, `transfers`=`transfers` + :amount WHERE `username` = :username";
$keys = array(":amount" => $amount, ":username" => $exchange_user);
sql_post($sql, $keys);

//Update Liquidation Price
update_liquidation_price($exchange_user);
?>