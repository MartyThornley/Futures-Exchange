<?php
require "../configuration.php";
$sql = "SELECT `price`, `amount`, `type`,  `trade_timestamp` FROM `Trades` WHERE `initiator` = 'true' ORDER BY `trade_timestamp` DESC LIMIT 30";
$trades = sql_get($sql, array());
for($i = 0; $i < count($trades); $i++){
	$trades[$i]['price'] = floatval($trades[$i]['price']);
	$trades[$i]['amount'] = intval($trades[$i]['amount']);
}

print(json_encode($trades));
?>