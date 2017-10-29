<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$servername = "Put your own host here";
$username = "Put your own user here";
$password = "Put your own database here";
$database = "Put your own password here";

$data = [];
$data['message'] = '';
$data['result'] = 'failure';
function exit_program(){
	global $data;
	print(json_encode(($data)));
	exit;
}

try {
  $conn = new PDO("mysql:host=$servername;dbname=" . $database, $username, $password);
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e){
  echo "Connection failed: " . $e->getMessage();
}

function generateRandomString($length = 10) {
  $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
  $charactersLength = strlen($characters);
  $randomString = '';
  for ($i = 0; $i < $length; $i++) {
      $randomString .= $characters[rand(0, $charactersLength - 1)];
  }
  return $randomString;
}

function sql_post($sql, $keys){
	global $conn;
	$query = $conn->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
	$query->execute($keys);
}

function sql_get($sql, $keys){
	global $conn;
	$query = $conn->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
	$query->execute($keys);
	$response = $query->fetchAll(PDO::FETCH_ASSOC);
	return $response;
}

function get_system(){
	$sql = "SELECT * FROM `System`";
	$system =  sql_get($sql, $data=[])[0];
	$system['initial'] = intval($system['initial']);
	$system['maintenance'] = intval($system['maintenance']);
	$system['api_limit'] = intval($system['api_limit']);
	$system['maker_fee'] = floatval($system['maker_fee']);
	$system['taker_fee'] = floatval($system['taker_fee']);
	return $system;
}

function get_user($username){
	$sql = "SELECT * FROM `Users` WHERE `username`=:username";
	$keys = array(':username' => $username);
	return sql_get($sql, $keys);
}

function authenticate_get_user($api_key, $api_secret){
	$sql = "SELECT * FROM `Users` WHERE `api_key`=:api_key AND `api_secret`=:api_secret";
	$keys = array(":api_key" => $api_key, ":api_secret" => $api_secret);
	$response = sql_get($sql, $keys);
	if(count($response) == 0){
		$response = false;
	} else {
		$response = $response[0];
		$response['balance'] = floatval($response['balance']);
		$response['margin_bitcoin'] = floatval($response['margin_bitcoin']);
		$response['margin_position'] = floatval($response['margin_position']);
		$response['api_used'] = intval($response['api_used']);
		$response['api_limit'] = intval($response['api_limit']);
	}
	return $response;
}

function api_limit_user($user_database){
	global $data;
	if(intval($user_database['api_used']) >= intval($user_database['api_limit'])){
		$data['message'] = 'You have exceeded your API limit of ' . $user_database['api_limit'] . ' requests per minute. Wait one minute for your API limit to reset.';
		$data['result'] = 'failure';
		exit_program();
	} else {
		$sql = "UPDATE `Users` SET `api_used` = `api_used` + 1 WHERE `username` = :username";
		$keys = array(":username" => $user_database['username']);
		sql_post($sql, $keys);
	}
}

function user_lock($user_database){
	global $data;
	if($user_database['locked'] == 'true'){
		$data['message'] = 'Your accounted is locked. Please contact support as soon as possible.';
		$data['result'] = 'failure';
		exit_program();
	}
}

function get_orderbook(){
	$order_book = [];
	$order_book['bids'] = [];
	$order_book['asks'] = [];
	$final_data = [];
	$final_data['bids'] = [];
	$final_data['asks'] = [];
	$sql  = "SELECT  `amount`, `price`, `side` FROM `Orderbook` WHERE `side` = 'buy' ORDER BY `price` DESC";
	$bids = sql_get($sql, array());
	foreach($bids as $bid){
		if(! isset($order_book['bids'][$bid['price']])){
			$order_book['bids'][$bid['price']] = 0;
		}
		$order_book['bids'][$bid['price']] += $bid['amount'];
	}
	$sql  = "SELECT  `amount`, `price`, `side` FROM `Orderbook` WHERE `side` = 'sell' ORDER BY `price` ASC";
	$asks = sql_get($sql, array());
	foreach($asks as $ask){
		if(! isset($order_book['asks'][$ask['price']])){
			$order_book['asks'][$ask['price']] = 0;
		}
		$order_book['asks'][$ask['price']] += $ask['amount'];
	}
	foreach($order_book['bids'] as $key => $value){
		$final_data['bids'][] = [round($key, 2), intval($value)];
	}
	foreach($order_book['asks'] as $key => $value){
		$final_data['asks'][] = [round($key, 2), intval($value)];
	}
	return $final_data;
}

function get_fair_value(){
	$sql  = "SELECT AVG(`exchange_mark`) FROM `Bit_Index`";
	$index_data = sql_get($sql, array());
	$fair_value = [];
	$fair_value['mark'] = round($index_data[0]['AVG(`exchange_mark`)'], 2);
	return $fair_value;
}

function get_available_balance($user_database){
	$mark_price = get_fair_value()['mark'];
	//Order Margin
	$sql = "SELECT SUM(`price` * `amount`) / SUM(`amount`), SUM(`amount`) FROM `Orderbook` WHERE `username` = :username AND `side` = 'buy'";
	$keys = array(":username" => $user_database['username']);
	$buy_data = sql_get($sql, $keys)[0];
	$buy_price = $buy_data['SUM(`price` * `amount`) / SUM(`amount`)'];
	$buy_amount = $buy_data['SUM(`amount`)'];
	if($buy_price == NULL){
		$buy_price = 0;
	}
	if($buy_amount == NULL){
		$buy_amount = 0;
	}
	$sql = "SELECT SUM(`price` * `amount`) / SUM(`amount`), SUM(`amount`) FROM `Orderbook` WHERE `username` = :username AND `side` = 'sell'";
	$keys = array(":username" => $user_database['username']);
	$sell_data = sql_get($sql, $keys)[0];
	$sell_price = $sell_data['SUM(`price` * `amount`) / SUM(`amount`)'];
	$sell_amount = $sell_data['SUM(`amount`)'];
	if($sell_price == NULL){
		$sell_price = 0;
	}
	if($sell_amount == NULL){
		$sell_amount = 0;
	}
	if($user_database['margin_position'] < 0){
		$buy_amount -= abs(intval($user_database['margin_position']));
		if($buy_amount < 0){
			$buy_amount = 0;
		}
	}
	if($user_database['margin_position'] > 0){
		$sell_amount -= abs(intval($user_database['margin_position']));
		if($sell_amount < 0){
			$sell_amount = 0;
		}
	}
	
	$common_amount = min($buy_amount, $sell_amount);
	$buy_amount -= $common_amount;
	$sell_amount -= $common_amount;
	$common_price = round(($buy_price + $sell_price) / 2, 2);
	if($common_amount != 0 and $common_price != 0){
		$common_margin = ($common_amount / $common_price) / $user_database['initial'];
	} else {
		$common_margin = 0;
	}
	
	if($buy_price == 0){
		$buy_price = 1;
	}
	
	if($sell_price == 0){
		$sell_price = 1;
	}
	
	$buy_margin = ($buy_amount / $buy_price) / $user_database['initial'];
	$sell_margin = ($sell_amount / $sell_price) / $user_database['initial'];
	$total_order_margin = round($buy_margin + $sell_margin + $common_margin, 8);
	
	
	//Position Margin
	$total_position_margin = (abs($user_database['margin_position']) / $mark_price) / $user_database['initial'];
	$total_unrealized_pl = -($user_database['margin_bitcoin'] + ($user_database['margin_position'] / $mark_price));
	$total_margin_balance = $user_database['balance'] + $total_unrealized_pl;
	$total_pl = $total_margin_balance - $user_database['deposits'] + $user_database['withdrawals'];
	$available_balance = $total_margin_balance - $total_order_margin - $total_position_margin;
	
	$response['margin_balance'] = round($total_margin_balance, 8);
	$response['available_balance'] = round($available_balance, 8);
	$response['position_margin'] = round($total_position_margin, 8);
	$response['order_margin'] = round($total_order_margin, 8);
	$response['unrealized_pl'] = round($total_unrealized_pl, 8);
	$response['realized_pl'] = round($total_pl, 8);
	$response['total_volume'] = intval($user_database['trade_volume']);
	return $response;
}

function get_order_imbalance($user_database){
	$sql = "SELECT SUM(`amount`) FROM `Orderbook` WHERE `username` = :username AND `side` = 'buy'";
	$keys = array(':username' => $user_database['username']);
	$bid_amount = sql_get($sql, $keys)[0]['SUM(`amount`)'];
	if($bid_amount == NULL){
		$bid_amount = 0;
	}
	$bid_amount = intval($bid_amount);
	$sql = "SELECT SUM(`amount`) FROM `Orderbook` WHERE `username` = :username AND `side` = 'sell'";
	$keys = array(':username' => $user_database['username']);
	$ask_amount = sql_get($sql, $keys)[0]['SUM(`amount`)'];
	if($ask_amount == NULL){
		$ask_amount = 0;
	}
	$ask_amount = intval($ask_amount);
	
	
	$imbalance = $bid_amount - $ask_amount + $user_database['margin_position'];
	return $imbalance;
}

function calculate_liquidation_price($price, $leverage, $side, $user_database){
	if(! isset($leverage)){
		$leverage = $user_database['initial'];
	} elseif(floatval($leverage) > $user_database['initial']){
		$leverage = $user_database['initial'];
	}
	
	if($side == 'buy'){
		$i = $leverage;
		$m = 1 / floatval($user_database['maintenance']);
		
		$liquidation_price = $price * (1 - $i) / (1 - $m);
	} else {
		$i = $leverage;
		print($i);
		print("\n");
		$m = 1 / floatval($user_database['maintenance']);
		$liquidation_price = $price * (1 + $i) / (1 + $m);
	}
	if($liquidation_price  < 0){
		$liquidation_price = 1;
	}
	return $liquidation_price;
}

function update_liquidation_price($username){
	//Not quite accurate
	global $conn;
	$user_database = get_user($username)[0];
	$available_balance = get_available_balance($user_database);
	$usable_balance = floatval($available_balance['margin_balance']) - floatval($available_balance['order_margin']);
	#print($usable_balance);
	if($usable_balance < 0){
		//Kill if in deep shit
		return;
	}
	
	/* if closed set 0 and return */
	if($user_database['margin_position'] == 0){
		$sql = "UPDATE `Users` SET `liquidation_price` = 0 WHERE `username` = :username";
		$keys = array(":username" => $user_database['username']);
		sql_post($sql, $keys);
		return;
	}
	
	$user_price = abs($user_database['margin_position']) / abs($user_database['margin_bitcoin']);
	if(intval($user_database['margin_position']) < 0){
		$user_side = 'sell';
	} else {
		$user_side = 'buy';
	}
	$user_leverage = $usable_balance / abs(floatval($user_database['margin_bitcoin']));
	$user_price = abs($user_database['margin_position']) / abs($user_database['margin_bitcoin']);
	
	//print($user_leverage);
	//print("\n");
	//var_dump($available_balance);
	//print($user_side);
	$liquidation_price = calculate_liquidation_price($user_price, $user_leverage, $user_side, $user_database);
	$sql = "UPDATE `Users` SET `liquidation_price` = :liquidation_price WHERE `username` = :username";
	$keys = array(":liquidation_price" => round($liquidation_price, 2), ":username" => $user_database['username']);
	sql_post($sql, $keys);
}
?>
