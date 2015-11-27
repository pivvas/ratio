<?php
/**
 *
 * Скрипт запускать по крон 1 раз в 30 дней
 *
 * Общиф алгоритм данного скрипта:
 * 1. Получаем брошенные корзины пользователей и их содержимое, с флагом DALYED=Y, за последние 30 дней
 * 2. Получаем заказы пользоватлей и их содержимое запоследние 30 дней
 * 3. Создаем результирующий массив, путем исключения из массива 1 значений массива 2
 * 4. Создаем почтовые события
 * 5. Отправляем письма
 *
 * Пути дальнейшей оптимизации функционала:
 * Необходимо при формровании заказа повесить на eventHandler функционал, который удалял бы доавленные в заказ товары
 * из отложенных товаров пользователя. Т.о. появится возможность убрать из данного скрипта пункты 2 и 3.
 *
 */

$_SERVER["DOCUMENT_ROOT"] = realpath(dirname(__FILE__) . "/../..");
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
define('CHK_EVENT', true);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

@set_time_limit(0);
@ignore_user_abort(true);

CModule::IncludeModule('sale');

// Получим дату, до которой нам необходимо получить отложенные товары.

$filterDate = (new \DateTime())
	->modify('-30 days')
	->format('d.m.Y');

$arFilter = [
	'>=DATE_UPDATE' => $filterDate,
];

// Получим брошенные корзины пользователей

$dbLeaveBasketResultList = CSaleBasket::GetLeave(
	array($by => $order),
	$arFilter,
	false,
	false
);

// Получим данные о корзинах пользователя

while ($arBasket = $dbLeaveBasketResultList->Fetch()) {

	if (intval($arBasket['USER_ID']) == 0)
		continue;

	// Создадим массив идентификаторов пользователей
	$arUserID[] = $arBasket['USER_ID'];

	$arFilterBasket = Array(
		"ORDER_ID" => false,
		"FUSER_ID" => $arBasket["FUSER_ID"],
		"LID" => $arBasket["LID"],
		"DELAY" => 'Y',
	);

	$arBasketItems = array();

	$dbB = CSaleBasket::GetList(
		array("ID" => "ASC"),
		$arFilterBasket,
		false,
		false,
		array("ID", "PRODUCT_ID", "NAME", "QUANTITY", "PRICE", "CURRENCY", "DETAIL_PAGE_URL", "LID", "CAN_BUY", "SUBSCRIBE", "DELAY", 'ORDER_ID')
	);

	// Создадим массив с отложенными товарами, распределенными по пользователям

	while ($arB = $dbB->Fetch())
		$arItemsByUser[$arBasket['USER_ID']][] = $arB;

}

// Получим e-mail'ы покупателей
$rsUsers = CUser::GetList(($by = "ID"), ($order = "desc"), ['ID' => implode('|', $arUserID)]);
while ($arUser = $rsUsers->Fetch()) {
	$arUsers[$arUser['ID']] = [
		'ID' => $arUser['ID'],
		'EMAIL' => $arUser['EMAIL'],
		'NAME' => $arUser['NAME'],
		'LAST_NAME' => $arUser['LAST_NAME'],
	];
}

// Получим список товаров пользователей, находящихся в заказах
foreach ($arUsers as $arUser) {
	$db_sales = CSaleOrder::GetList(false, ["USER_ID" => $arUser['ID'], ">=DATE_INSERT" => $filterDate]);
	while ($ar_sales = $db_sales->Fetch()) {
		$arFilterBasket = Array(
			"ORDER_ID" => $ar_sales['ID'],
		);

		$arBasketItems = array();

		$dbB = CSaleBasket::GetList(
			array("ID" => "ASC"),
			$arFilterBasket,
			false,
			false,
			array("ID", "PRODUCT_ID", "NAME", "QUANTITY", "PRICE", "CURRENCY", "DETAIL_PAGE_URL", "LID", "CAN_BUY", "SUBSCRIBE", "DELAY", 'ORDER_ID')
		);

		// Создадим массив с товарами, которые находятся в заказах, распределенными по пользователям

		while ($arB = $dbB->Fetch())
			$arOrderedItemsByUser[$arUser['ID']][$arB['PRODUCT_ID']] = $arB['PRODUCT_ID'];
	}
}

// Отфильтруем из отложенных товаров те, которые были отправленны в заказ. Для этого создадим новый массив
foreach ($arItemsByUser as $userID => $userItems) {
	foreach ($userItems as $itemIndex => $arItem) {
		if (!in_array($arItem['PRODUCT_ID'], $arOrderedItemsByUser[$userID])) {
			$arItemsByUserFinal[$userID][] = $arItem;
		}
	}
}

// Создадим почтовые события
foreach ($arItemsByUserFinal as $userID => $arItems) {

	$arUser = $arUsers[$userID];

	$products = '';

	foreach ($arItems as $arItem) {
		$products .= $arItem['NAME'] . "\r\n";
	}


	$arEventFields = array(
		"NAME" => $arUser['NAME'],
		"LAST_NAME" => $arUser['LAST_NAME'],
		"EMAIL_TO" => $arUser['EMAIL'],
		"PRODUCT_LIST" => $products,
	);

	CEvent::Send("REMIND_BASKET", 's1', $arEventFields);

}

// Отправим письма
CEvent::CheckEvents();