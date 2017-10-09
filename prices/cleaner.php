<?php

session_start();

$siteFolder = __DIR__ . '/../..';

$_SERVER["DOCUMENT_ROOT"] = $siteFolder;
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
define("LANG", "s1");

define('IBLOCK_ID', 22);

require($_SERVER["DOCUMENT_ROOT"]. "/bitrix/modules/main/include/prolog_before.php");
require ('src/helper.php');
CModule::IncludeModule('iblock');

if ('POST' == $_SERVER['REQUEST_METHOD']) {

    $brands = array();

    foreach ($_POST['brands'] as $brandId) {
        $brands[] = $brandId;
    }

    // todo найти все товары брендов и удалить им цены
    Cmodule::IncludeModule("catalog");

    $brandFilter = array(
        "LOGIC" => "OR"
    );
    foreach ($brands as $brand) {
        $brandFilter[] = array("PROPERTY_MANUFACTURER_CATALOG" => $brand);
    }
    $arSelect = Array("ID", "PROPERTY_article_price");
    $arFilter = Array(
        "IBLOCK_ID"=> IBLOCK_ID,
        $brandFilter,
    );

    $res = CIBlockElement::GetList(Array(), $arFilter, false, Array("nPageSize"=>5000), $arSelect);
    $i = 0;
    $J = 0;
    while($ob = $res->GetNextElement())
    {
        $arFields = $ob->GetFields();
        $PRODUCT_ID = $arFields['ID'];
        CPrice::DeleteByProduct($PRODUCT_ID);

        $articlePrices = array();
        $articulRes = CIBlockElement::GetProperty(IBLOCK_ID, $PRODUCT_ID, "sort", "asc", array("CODE" => "article_price"));
        while ($ob = $articulRes->GetNext()) {
            $articlePrices[] = $ob['VALUE'];
        }

        // Обновляем PROPERTY_article_price
        $arArticles = array();
        foreach ($articlePrices as $articlePrice) {
            $articlePriceArray = explode(' | ', $articlePrice);
            $articlePriceArray[2] = '0';
            $arArticles[] = array("VALUE" => implode(' | ', $articlePriceArray), "DESCRIPTION" => "");
            $j++;
        }

        CIBlockElement::SetPropertyValueCode($PRODUCT_ID, 'article_price', $arArticles);
        $i++;
    }

    CIBlock::clearIblockTagCache(IBLOCK_ID);

	$_SESSION['message'] = array('type' => 'success', 'text' => ' Цены удалены у '.$i.' товаров и '.$j.' вариантов товаров');
	header('location: '. $_SERVER['REQUEST_URI']);
	exit;
}


$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
unset($_SESSION['message']);

global $USER;

$title = 'Удаление цен в каталоге';

include('src/view/header.view.php');

include('src/view/cleaner.view.php');

include('src/view/footer.view.php');

require(__DIR__. "/../../bitrix/modules/main/include/epilog_after.php");