<?php
require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php';

CModule::IncludeModule('sale');

$dbBasketList = CSaleBasket::GetList(
	array("ID" => "ASC"),
	array(
		//"FUSER_ID" => $fuserID,
		"LID" => 's1',
		// "CAN_BUY" => "Y",
		// "DELAY" => "N",
		"ORDER_ID" => false,
	),
	false,
	false,
	array("ID", 'USER_ID', 'FUSER_ID', "PRODUCT_ID", "CAN_BUY", "DELAY", "SUBSCRIBE", "QUANTITY")
);

$arID = Array();

while($arItems = $dbBasketList->Fetch())
{
	_p($arItems);

}