<?php
function _p($array, $isDie)
{
	echo '<pre>';
	print_r($array);
	echo '</pre>';

	if ($isDie) {
		die;
	}
}


AddEventHandler("sale", "OnBasketAdd", "AddDiscount2Ever10Item" ;);
AddEventHandler("sale", "OnBasketUpdate", "AddDiscount2Ever10Item" ;);

function AddDiscount2Ever10Item($ID, $arFields)
{
	// echo '<pre>';
	// print_r($arFields);
	// echo '</pre>';

	$arBasketItem = CSaleBasket::GetByID($ID);

	$arSelect = Array("ID", "NAME");
	$arFilter = Array("IBLOCK_ID" => 5, "ID" => $arBasketItem["PRODUCT_ID"], "SECTION_ID" => 582, "ACTIVE_DATE" => "Y", "ACTIVE" => "Y" ); // Ставим скидку для раздела 582
	$res = CIBlockElement::GetList(Array(), $arFilter, false, Array("nPageSize" => 1), $arSelect);
	while ($ob = $res->GetNextElement()) {
		$arSelectFields = $ob->GetFields();
		// print_r($arSelectFields);
	}


	if ($arFields["QUANTITY"] >= 10 && $arSelectFields["ID"] > 0) {
		$discountQuantity = floor($arFields["QUANTITY"] / 10);
		$TotalDiscount = $arFields["PRICE"] * $discountQuantity;
		$ItemDiscount = $TotalDiscount / $arFields["QUANTITY"];

		CSaleBasket::Update($ID, Array(
			"PRICE" => (ceil($arFields["PRICE"] - $ItemDiscount)),
			"DISCOUNT_NAME" => "Каждый десятый товар бесплатно",
			"DISCOUNT_PRICE"=>$ItemDiscount)
		);

}
}