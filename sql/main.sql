CREATE TABLE Bit_Index (
	exchange_name varchar(255),
	exchange_mark double,
	exchange_bid double,
	exchange_ask double,
	exchange_last double,
	exchange_time double,
	exchange_volume double,
	PRIMARY KEY(`exchange_name`)
)
CREATE TABLE `Users` (
	balance double,
	margin_bitcoin double,
	margin_position double,
	api_key varchar(255),
	api_secret varchar(255),
	web_api_key varchar(255),
	web_api_secret varchar(255),
	api_used double,
	api_limit double,
	username varchar(255),
	password_hash varchar(255),
	email varchar(255),
	maker_fee double,
	taker_fee double,
	trade_volume double,
	deposits double,
	withdrawals double,
	fees_paid double,
	bitcoin_address varchar(255),
	locked varchar(255),
	transfers double,
	PRIMARY KEY (username)
)
ALTER TABLE `Users` ADD `locked` varchar(255)
ALTER TABLE `Users` ADD `initial` double
ALTER TABLE `Users` ADD `maintenance` double
CREATE TABLE Orderbook (
	username varchar(255),
	amount double,
	bitcoin_amount double,
	price double,
	order_timestamp double,
	side varchar(255),
	order_id varchar(255),
	PRIMARY KEY (`order_id`)
)
CREATE TABLE System (
	inital double,
	maintenance double,
	maker_fee double,
	taker_fee double,
	api_limit double
)
CREATE TABLE Trades (
	username varchar(255),
	price double,
	amount double,
	type varchar(255),
	bitcoin_amount double,
	trade_id varchar(255),
	trade_timestamp double,
	fee double,
	initiator varchar(255)
)
CREATE TABLE Orders (
	username varchar(255),
	order_id varchar(255),
	side varchar(255),
	type varchar(255),
	status varchar(255),
	initial_quantity double,
	remaining_quantity double,
	executed_quantity double,
	bitcoin_amount double,
	created_time double,
	terminated_time double,
	PRIMARY KEY (`order_id`)
)
CREATE TABLE Transfers (
	username varchar(255),
	type varchar(255),
	amount double,
	timestamp double,
	transfer_id varchar(255),
	PRIMARY KEY(`transfer_id`)
)
CREATE TABLE Swap (
	fair_value double,
	mark_value double,
	difference double,
	longs varchar(255),
	shorts varchar(255),
	timestamp double,
	PRIMARY KEY(`timestamp`)
)
/* status: accepted, rejected, cancelled, executed */