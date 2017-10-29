<?php
require "../configuration.php";

/*
response fields
message
status
remaining
executed
side
price
result
order_id
*/

$data = [];
$data['message'] = '';
$data['result'] = 'failure';

$api_key = $_SERVER['HTTP_API_KEY'];
$api_secret = $_SERVER['HTTP_API_SECRET'];
$side = $_POST['side'];
$amount = $_POST['amount'];
$type = $_POST['type'];

if($type == 'limit'){
	$price = $_POST['price'];
}
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
user_lock($user_database);
if(! isset($side)){
	$data['message'] = "You must provide a side. Valid Options: buy, sell";
	exit_program();
}
if($side != 'buy' and $side != 'sell'){
	$data['message'] = "Parameter side must be set to: buy or sell";
	exit_program();
}
if(! isset($amount)){
	$data['message'] = "Amount must be set. Example: 10";
	exit_program();
}
if(intval($amount) <= 0){
	$data['message'] = "Amount must be greater than 0. Example: 10";
	exit_program();
}
if(! isset($type)){
	$data['message'] = "You must provide a 'type'. Valid Options: 'market', 'limit'";
	exit_program();
}
if($type != 'market' and $type != 'limit'){
	$data['message'] = "Parameter 'type' must be set to: 'market' or 'limit'";
	exit_program();
}
if($type == 'limit' and ! isset($price)){
	$data['message'] = "A price must be set when using a limit order.";
	exit_program();
}
if($type == 'limit' and floatval($price) <= 0){
	$data['message'] = 'Price must be greater than 0.';
	exit_program();
}
$amount = intval($amount);
if($type == 'limit'){
	$price = round(floatval($price), 2);
}

/* Code Saving */
function handle_place_limit($database, $amount, $side, $price, $order_id, $bitcoin_amount){
	global $data;
	$order_timestamp = time();
	$sql = "INSERT INTO `Orderbook`(`username`, `amount`, `bitcoin_amount`, `price`, `order_timestamp`, `side`, `order_id`) VALUES (:username, :amount, :bitcoin_amount, :price, :order_timestamp, :side, :order_id)";
	$keys = array(":username" => $database['username'], ":amount" => $amount, ":side" => $side, ":price" => $price, ":order_id" => $order_id, ":bitcoin_amount" => $bitcoin_amount, ":order_timestamp" => $order_timestamp);
	sql_post($sql, $keys);
	update_liquidation_price($database['username']);
	
	//Update Orders Table
	$sql = "INSERT INTO `Orders`(`username`, `order_id`, `side`, `type`, `status`, `initial_quantity`, `remaining_quantity`, `executed_quantity`, `bitcoin_amount`, `created_time`, `terminated_time`) VALUES (:username, :order_id, :side, 'limit', 'accepted', :quantity, :quantity, 0, 0, :order_timestamp, 0)";
	$keys = array(":username" => $database['username'], ":order_id" => $order_id, ":side" => $side, ":quantity" => $amount, ":order_timestamp" => $order_timestamp);
	sql_post($sql, $keys);
}

function handle_process_trade($taker_database, $maker_database, $side, $amount, $matched_order){
	$order_id = $matched_order['order_id'];
	$trade_id = generateRandomString(8);
	$trade_timestamp = time();
	$margin_bitcoin_amount = round($amount / floatval($matched_order['price']), 8);
	
	if($side == 'buy'){
		$maker_bitcoin_amount = $margin_bitcoin_amount;
		$maker_margin_position = -$amount;
		$taker_bitcoin_amount = -$margin_bitcoin_amount;
		$taker_margin_position = $amount;
		$resting_side = 'sell';
	} else {
		$maker_bitcoin_amount = -$margin_bitcoin_amount;
		$maker_margin_position = $amount;
		$taker_bitcoin_amount = $margin_bitcoin_amount;
		$taker_margin_position = -$amount;
		$resting_side = 'buy';
	}
	
	$maker_fee = round(abs($margin_bitcoin_amount) * $maker_database['maker_fee'], 8);
	$taker_fee = round(abs($margin_bitcoin_amount) * $taker_database['taker_fee'], 8);
	
	//Update Resting Order
	if($amount == $matched_order['amount']){
		$sql = "DELETE FROM `Orderbook` WHERE `order_id` = :order_id";
		$keys = array(":order_id" => $matched_order['order_id']);
		sql_post($sql, $keys);
	} else {
		$new_order_bitcoin_amount = round(($matched_order['amount'] - $amount) / $matched_order['price'], 8);
		$sql = "UPDATE `Orderbook` SET `amount`=`amount` - :trade_amount, `bitcoin_amount`=:bitcoin_amount WHERE `order_id` = :order_id";
		$keys = array(":trade_amount" => $amount, ":bitcoin_amount" => $new_order_bitcoin_amount, ":order_id" => $matched_order['order_id']);
		sql_post($sql, $keys);
	}

	//Taker Records
	$sql = "INSERT INTO `Trades`(`username`, `price`, `amount`, `type`, `bitcoin_amount`, `trade_id`, `trade_timestamp`, `fee`, `initiator`) VALUES (:username, :price, :amount, :type, :bitcoin_amount, :trade_id, :trade_timestamp, :fee, :initator)";
	$keys = array(":username" => $taker_database['username'], ":price" => $matched_order['price'], ":amount" => $amount, "type" => $side, ":bitcoin_amount" => $taker_bitcoin_amount, ":trade_id" => $trade_id, ":trade_timestamp" => $trade_timestamp, ":fee" => $taker_fee, ":initator" => 'true');
	sql_post($sql, $keys);
	
	//Maker Records
	$sql = "INSERT INTO `Trades`(`username`, `price`, `amount`, `type`, `bitcoin_amount`, `trade_id`, `trade_timestamp`, `fee`, `initiator`) VALUES (:username, :price, :amount, :type, :bitcoin_amount, :trade_id, :trade_timestamp, :fee, :initator)";
	$keys = array(":username" => $maker_database['username'], ":price" => $matched_order['price'], ":amount" => $amount, "type" => $resting_side, ":bitcoin_amount" => $maker_bitcoin_amount, ":trade_id" => $trade_id, ":trade_timestamp" => $trade_timestamp, ":fee" => $maker_fee, ":initator" => 'false');
	sql_post($sql, $keys);
	
	//Update Taker Database
	$sql = "UPDATE `Users` SET  `balance` = `balance` - :fee, `margin_bitcoin`=`margin_bitcoin` + :margin_bitcoin, `margin_position`=`margin_position` + :margin_position, `trade_volume`=`trade_volume` + :amount, `fees_paid`=`fees_paid` + :fee WHERE `username` = :username";
	$keys = array(":margin_bitcoin" => $taker_bitcoin_amount, ":margin_position" => $taker_margin_position, ":amount" => abs($amount), ":fee" => $taker_fee, ":username" => $taker_database['username']);
	sql_post($sql, $keys);
	
	//Update Maker Database
	$sql = "UPDATE `Users` SET  `balance` = `balance` - :fee, `margin_bitcoin`=`margin_bitcoin` + :margin_bitcoin, `margin_position`=`margin_position` + :margin_position, `trade_volume`=`trade_volume` + :amount, `fees_paid`=`fees_paid` + :fee WHERE `username` = :username";
	$keys = array(":margin_bitcoin" => $maker_bitcoin_amount, ":margin_position" => $maker_margin_position, ":amount" => abs($amount), ":fee" => $maker_fee, ":username" => $maker_database['username']);
	sql_post($sql, $keys);
	
	//Update Taker Liquidation Price
	update_liquidation_price($taker_database['username']);
	
	//Update Maker Liquidation Price
	update_liquidation_price($maker_database['username']);
	
	//Update Taker PL
	if($taker_database['margin_position'] + $taker_margin_position == 0){
		//Transfer to main balance
		$sql = "UPDATE `Users` SET `balance`=`balance` - `margin_bitcoin`, `margin_bitcoin` = `margin_bitcoin`-`margin_bitcoin` WHERE `username` = :username";
		$keys = array(":username" => $taker_database['username']);
		sql_post($sql, $keys);
	}
	
	//Update Maker Pl
	if($maker_database['margin_position'] + $maker_margin_position == 0){
		//Transfer to main balance
		$sql = "UPDATE `Users` SET `balance`=`balance` - `margin_bitcoin`, `margin_bitcoin` = `margin_bitcoin`-`margin_bitcoin` WHERE `username` = :username";
		$keys = array(":username" => $maker_database['username']);
		sql_post($sql, $keys);
	}
	
	//Update Resting Order Table
	$sql = "UPDATE `Orders` SET `remaining_quantity` = `remaining_quantity` - :quantity, `executed_quantity` = `executed_quantity` + :quantity, `bitcoin_amount` = `bitcoin_amount` + :bitcoin_amount WHERE `order_id` = :order_id";
	$keys = array(":quantity" => $amount, ":bitcoin_amount" => abs($margin_bitcoin_amount), ":order_id" => $order_id);
	sql_post($sql, $keys);
}

function handle_rejected_order($username, $order_id, $side, $type, $amount){
	$sql = "INSERT INTO `Orders`(`username`, `order_id`, `side`, `type`, `status`, `initial_quantity`, `remaining_quantity`, `executed_quantity`, `bitcoin_amount`, `created_time`, `terminated_time`) VALUES (:username, :order_id, :side, :type, 'rejected', :amount, 0, 0, 0, :time, :time)";
	$keys = array(":username" => $username, ":order_id" => $order_id, ":side" => $side, ":type" => $type, ":amount" => $amount, ":time" => time());
	sql_post($sql, $keys);
}


if($type == 'limit' and $side == 'buy'){
	//Buy Limit
	/* MARGIN CHECK */
	$imbalance = get_order_imbalance($user_database);
	$margin_amount = $imbalance + $amount;
	if($margin_amount <= 0){
		$margin_amount = 0;
	} elseif($imbalance < 0) {
		$margin_amount = $imbalance + $amount;
	} else {
		$margin_amount = $amount;
	}
	$margin_needed = ($margin_amount / $price) / intval($user_database['initial']);
	$available_balance = floatval(get_available_balance($user_database)['available_balance']);
	if($margin_needed > $available_balance){
		$data['message'] = 'Insufficient margin for this order';
		$data['status'] = 'rejected';
		handle_rejected_order($user_database['username'], generateRandomString(8), $side, $type, $amount);
		exit_program();
	}
	
	$bitcoin_amount = round($amount / $price, 8);
	$inital_amount = $amount;
	$order_id = generateRandomString(8);
	$sql = "SELECT * FROM `Orderbook` WHERE `side`='sell' AND `price` <= :price ORDER BY `price` ASC, `order_timestamp` ASC";
	$keys = array(":price" => $price);
	$matched_orders = sql_get($sql, $keys);
	$total_bitcoin_amount_executed = 0;
	
	if(count($matched_orders) == 0){
		handle_place_limit($user_database, $amount, $side, $price, $order_id, $bitcoin_amount);
		$data['message'] = 'Order Placed';
		$data['status'] = 'accepted';
		$data['remaining'] = $amount;
		$data['executed'] = 0;
		$data['side'] = $side;
		$data['price'] = $price;
		$data['result'] = 'success';
		$data['order_id'] = $order_id;
		exit_program();
	} else {
		foreach($matched_orders as $matched_order){
			if($amount >= $matched_order['amount']){
				$trade_amount = intval($matched_order['amount']);
				$amount -= intval($matched_order['amount']);
			} else {
				$trade_amount = $amount;
				$amount = 0;
			}
			
			$trade_bitcoin_amount = round($trade_amount / floatval($matched_order['price']), 8);
			$total_bitcoin_amount_executed += $trade_bitcoin_amount;
			$trade_price = floatval($matched_order['price']);
	
			$maker_user_database = get_user($matched_order['username'])[0];
			handle_process_trade($user_database, $maker_user_database, $side, $trade_amount, $matched_order);
			
			if($amount == 0){
				break;
			}
		}
	}
	
	if($amount != 0){
		//Place New
		$data['status'] = 'accepted';
		$data['message'] = 'Order partially executed. ' . $amount . ' units remain.';
		$data['remaining'] = $amount;
		$data['executed'] = $inital_amount - $amount;
		handle_place_limit($user_database, $amount, $side, $price, $order_id, $bitcoin_amount);
		$sql = "UPDATE `Orders` SET `initial_quantity` = :initial_quantity, `executed_quantity` = :executed_quantity, `bitcoin_amount` = :bitcoin_amount WHERE `order_id` = :order_id";
		$keys = array(":initial_quantity" => $inital_amount, ":executed_quantity" => intval($inital_amount - $amount), ":bitcoin_amount" => abs($total_bitcoin_amount_executed), ":order_id" => $order_id);
		sql_post($sql, $keys);
	} else {
		//Enter Limit Market Order Into Table
		$sql = "INSERT INTO `Orders`(`username`, `order_id`, `side`, `type`, `status`, `initial_quantity`, `remaining_quantity`, `executed_quantity`, `bitcoin_amount`, `created_time`, `terminated_time`) VALUES (:username, :order_id, :side, 'limit', 'executed', :initial_quantity, 0, :initial_quantity, :bitcoin_amount, :trade_time, :trade_time)";
		$keys = array(":username" => $user_database['username'], ":order_id" => $order_id, ":side" => $side, ":initial_quantity" => $inital_amount, ":bitcoin_amount" => abs($bitcoin_amount), ":trade_time" => time());
		sql_post($sql, $keys);
		$data['message'] = 'Order fully executed.';
		$data['status'] = 'accepted';
		$data['remaining'] = 0;
		$data['executed'] = $inital_amount;
	}
	$average_execution_price = round((($inital_amount - $amount) / $total_bitcoin_amount_executed), 2);
	$data['side'] = $side;
	$data['avg_price'] = $average_execution_price;
	$data['result'] = 'success';
	$data['order_id'] = $order_id;
	$data['price'] = $price;
	exit_program();
}

if($type == 'limit' and $side == 'sell'){
	//Sell Limit
	$imbalance = get_order_imbalance($user_database);
	$margin_amount = $imbalance - $amount;
	if($margin_amount >= 0){
		$margin_amount = 0;
	} elseif($imbalance > 0) {
		$margin_amount = abs($imbalance - $amount);
	} else {
		$margin_amount = $amount;
	}
	$margin_needed = ($margin_amount / $price) / intval($user_database['initial']);
	$available_balance = floatval(get_available_balance($user_database)['available_balance']);
	if($margin_needed > $available_balance){
		$data['message'] = 'Insufficient margin for this order';
		$data['status'] = 'rejected';
		handle_rejected_order($user_database['username'], generateRandomString(8), $side, $type, $amount);
		exit_program();
	}
	
	/* MARGIN CHECK */
	$bitcoin_amount = round($amount / $price, 8);
	$inital_amount = $amount;
	$order_id = generateRandomString(8);
	$sql = "SELECT * FROM `Orderbook` WHERE `side`='buy' AND `price` >= :price ORDER BY `price` DESC, `order_timestamp` ASC";
	$keys = array(":price" => $price);
	$matched_orders = sql_get($sql, $keys);
	$total_bitcoin_amount_executed = 0;
	
	if(count($matched_orders) == 0){
		handle_place_limit($user_database, $amount, $side, $price, $order_id, $bitcoin_amount);
		$data['message'] = 'Order Placed';
		$data['status'] = 'accepted';
		$data['remaining'] = $amount;
		$data['executed'] = 0;
		$data['side'] = $side;
		$data['price'] = $price;
		$data['result'] = 'success';
		$data['order_id'] = $order_id;
		exit_program();
	} else {
		foreach($matched_orders as $matched_order){
			if($amount >= $matched_order['amount']){
				$trade_amount = intval($matched_order['amount']);
				$amount -= intval($matched_order['amount']);
			} else {
				$trade_amount = $amount;
				$amount = 0;
			}
			
			$trade_bitcoin_amount = round($trade_amount / floatval($matched_order['price']), 8);
			$total_bitcoin_amount_executed += $trade_bitcoin_amount;
			$trade_price = floatval($matched_order['price']);
	
			$maker_user_database = get_user($matched_order['username'])[0];
			handle_process_trade($user_database, $maker_user_database, $side, $trade_amount, $matched_order);
			
			if($amount == 0){
				break;
			}
		}
	}
	
	if($amount != 0){
		//Place New
		$data['status'] = 'accepted';
		$data['message'] = 'Order partially executed. ' . $amount . ' units remain.';
		$data['remaining'] = $amount;
		$data['executed'] = $inital_amount - $amount;
		handle_place_limit($user_database, $amount, $side, $price, $order_id, $bitcoin_amount);
		$sql = "UPDATE `Orders` SET `initial_quantity` = :initial_quantity, `executed_quantity` = :executed_quantity, `bitcoin_amount` = :bitcoin_amount WHERE `order_id` = :order_id";
		$keys = array(":initial_quantity" => $inital_amount, ":executed_quantity" => intval($inital_amount - $amount), ":bitcoin_amount" => abs($total_bitcoin_amount_executed), ":order_id" => $order_id);
		sql_post($sql, $keys);
	} else {
		//Enter Limit Market Order Into Table
		$sql = "INSERT INTO `Orders`(`username`, `order_id`, `side`, `type`, `status`, `initial_quantity`, `remaining_quantity`, `executed_quantity`, `bitcoin_amount`, `created_time`, `terminated_time`) VALUES (:username, :order_id, :side, 'limit', 'executed', :initial_quantity, 0, :initial_quantity, :bitcoin_amount, :trade_time, :trade_time)";
		$keys = array(":username" => $user_database['username'], ":order_id" => $order_id, ":side" => $side, ":initial_quantity" => $inital_amount, ":bitcoin_amount" => abs($bitcoin_amount), ":trade_time" => time());
		sql_post($sql, $keys);
		$data['message'] = 'Order fully executed.';
		$data['status'] = 'accepted';
		$data['remaining'] = 0;
		$data['executed'] = $inital_amount;
	}
	$average_execution_price = round((($inital_amount - $amount) / $total_bitcoin_amount_executed), 2);
	$data['side'] = $side;
	$data['avg_price'] = $average_execution_price;
	$data['result'] = 'success';
	$data['order_id'] = $order_id;
	$data['price'] = $price;
	exit_program();
}

if($type == 'market' and $side == 'buy'){
	$order_book = get_orderbook();
	$total_ask = 0;
	foreach($order_book['bids'] as $resting_order){
		$total_ask += $resting_order[1];
	}
	if($amount > $total_ask){
		$data['message'] = 'This order has been rejected because it is larger than the total depth of the exchange';
		$data['status'] = 'rejected';
		handle_rejected_order($user_database['username'], generateRandomString(8), $side, $type, $amount);
		exit_program();
	}
	$order_id = generateRandomString(8);
	$margin_amount = $amount;
	if(intval($user_database['margin_position']) < 0){
		$margin_amount = $amount - abs($user_database['margin_position']);
	}
	$margin_buy_bitcoin_amount = 0;
	$total_bitcoin_amount_executed = 0;
	$inital_amount = $amount;
	$closing = 'false';
	if($margin_amount <= 0){
		$margin_amount = 0;
		$closing = 'true';
	}
	
	if($margin_amount != 0){
		foreach($order_book['asks'] as $resting_order){
			if($margin_amount >= $resting_order[1]){
				$individual_trade_amount = $resting_order[1];
			} else {
				$individual_trade_amount = $margin_amount;
			}
			$margin_buy_bitcoin_amount += ($individual_trade_amount / $resting_order[0]);
			$margin_amount -= $individual_trade_amount;
			
			if($margin_amount == 0){
				break;
			}
		}
	}
	
	/* MARGIN CHECK */
	$available_balance = floatval(get_available_balance($user_database)['available_balance']);
	$margin_buy_bitcoin_amount = $margin_buy_bitcoin_amount / intval($user_database['initial']);
	if($margin_buy_bitcoin_amount > $available_balance and $closing == 'false'){
		$data['message'] = 'Insufficient margin for this order';
		$data['status'] = 'rejected';
		handle_rejected_order($user_database['username'], generateRandomString(8), $side, $type, $amount);
		exit_program();
	}
	
	$sql = "SELECT * FROM `Orderbook` WHERE `side`='sell' ORDER BY `price` ASC, `order_timestamp` ASC";
	$matched_orders = sql_get($sql, array());
	foreach($matched_orders as $matched_order){
		if($amount >= $matched_order['amount']){
			$trade_amount = intval($matched_order['amount']);
			$amount -= intval($matched_order['amount']);
		} else {
			$trade_amount = $amount;
			$amount = 0;
		}
		
		$trade_bitcoin_amount = round($trade_amount / floatval($matched_order['price']), 8);
		$total_bitcoin_amount_executed += $trade_bitcoin_amount;
		$trade_price = floatval($matched_order['price']);

		$maker_user_database = get_user($matched_order['username'])[0];
		handle_process_trade($user_database, $maker_user_database, $side, $trade_amount, $matched_order);
		
		if($amount == 0){
			break;
		}
	}
	//Enter Market Order Into Table
	$sql = "INSERT INTO `Orders`(`username`, `order_id`, `side`, `type`, `status`, `initial_quantity`, `remaining_quantity`, `executed_quantity`, `bitcoin_amount`, `created_time`, `terminated_time`) VALUES (:username, :order_id, :side, 'market', 'executed', :initial_quantity, 0, :initial_quantity, :bitcoin_amount, :trade_time, :trade_time)";
	$keys = array(":username" => $user_database['username'], ":order_id" => $order_id, ":side" => $side, ":initial_quantity" => $inital_amount, ":bitcoin_amount" => abs($total_bitcoin_amount_executed), ":trade_time" => time());
	sql_post($sql, $keys);
	$average_execution_price = round(($inital_amount / $total_bitcoin_amount_executed), 2);
	$data['message'] = 'Order fully executed.';
	$data['status'] = 'accepted';
	$data['remaining'] = 0;
	$data['executed'] = $inital_amount;
	$data['side'] = $side;
	$data['avg_price'] = $average_execution_price;
	$data['result'] = 'success';
	$data['order_id'] = $order_id;
	exit_program();
}

if($type == 'market' and $side == 'sell'){
	$order_book = get_orderbook();
	$total_bid = 0;
	foreach($order_book['bids'] as $resting_order){
		$total_bid += $resting_order[1];
	}
	if($amount > $total_bid){
		$data['message'] = 'This order has been rejected because it is larger than the total depth of the exchange';
		$data['status'] = 'rejected';
		handle_rejected_order($user_database['username'], generateRandomString(8), $side, $type, $amount);
		exit_program();
	}
	$order_id = generateRandomString(8);
	$margin_amount = $amount;
	if(intval($user_database['margin_position']) > 0){
		$margin_amount = $amount - abs($user_database['margin_position']);
	}
	$margin_sell_bitcoin_amount = 0;
	$total_bitcoin_amount_executed = 0;
	$inital_amount = $amount;
	$closing = 'false';
	if($margin_amount <= 0){
		$margin_amount = 0;
		$closing = 'true';
	}

	if($margin_amount != 0){
		foreach($order_book['bids'] as $resting_order){
			if($margin_amount >= $resting_order[1]){
				$individual_trade_amount = $resting_order[1];
			} else {
				$individual_trade_amount = $margin_amount;
			}
			$margin_sell_bitcoin_amount += ($individual_trade_amount / $resting_order[0]);
			$margin_amount -= $individual_trade_amount;
			
			if($margin_amount == 0){
				break;
			}
		}
	}
	
	/* MARGIN CHECK */
	$available_balance = floatval(get_available_balance($user_database)['available_balance']);
	$margin_sell_bitcoin_amount = $margin_sell_bitcoin_amount / intval($user_database['initial']);
	if($margin_sell_bitcoin_amount > $available_balance and $closing == 'false'){
		$data['message'] = 'Insufficient margin for this order';
		$data['status'] = 'rejected';
		handle_rejected_order($user_database['username'], generateRandomString(8), $side, $type, $amount);
		exit_program();
	}
	
	$sql = "SELECT * FROM `Orderbook` WHERE `side`='buy' ORDER BY `price` DESC, `order_timestamp` ASC";
	$matched_orders = sql_get($sql, array());
	foreach($matched_orders as $matched_order){
		if($amount >= $matched_order['amount']){
			$trade_amount = intval($matched_order['amount']);
			$amount -= intval($matched_order['amount']);
		} else {
			$trade_amount = $amount;
			$amount = 0;
		}
		
		$trade_bitcoin_amount = round($trade_amount / floatval($matched_order['price']), 8);
		$total_bitcoin_amount_executed += $trade_bitcoin_amount;
		$trade_price = floatval($matched_order['price']);

		$maker_user_database = get_user($matched_order['username'])[0];
		handle_process_trade($user_database, $maker_user_database, $side, $trade_amount, $matched_order);
		
		if($amount == 0){
			break;
		}
	}
	//Enter Market Order Into Table
	$sql = "INSERT INTO `Orders`(`username`, `order_id`, `side`, `type`, `status`, `initial_quantity`, `remaining_quantity`, `executed_quantity`, `bitcoin_amount`, `created_time`, `terminated_time`) VALUES (:username, :order_id, :side, 'market', 'executed', :initial_quantity, 0, :initial_quantity, :bitcoin_amount, :trade_time, :trade_time)";
	$keys = array(":username" => $user_database['username'], ":order_id" => $order_id, ":side" => $side, ":initial_quantity" => $inital_amount, ":bitcoin_amount" => abs($total_bitcoin_amount_executed), ":trade_time" => time());
	sql_post($sql, $keys);
	$average_execution_price = round(($inital_amount / $total_bitcoin_amount_executed), 2);
	$data['message'] = 'Order fully executed.';
	$data['status'] = 'accepted';
	$data['remaining'] = 0;
	$data['executed'] = $inital_amount;
	$data['side'] = $side;
	$data['avg_price'] = $average_execution_price;
	$data['result'] = 'success';
	$data['order_id'] = $order_id;
	exit_program();
}
?>