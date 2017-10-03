<?php

session_start();

$siteFolder = __DIR__ . '/../..';

$_SERVER["DOCUMENT_ROOT"] = $siteFolder;
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
define("LANG", "s1");

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
    $arSelect = Array("ID");
    $arFilter = Array(
        "IBLOCK_ID"=> 22,
        $brandFilter,
    );

    $res = CIBlockElement::GetList(Array(), $arFilter, false, Array("nPageSize"=>5000), $arSelect);
    $i = 0;
    while($ob = $res->GetNextElement())
    {
        $arFields = $ob->GetFields();
        CPrice::DeleteByProduct($arFields['ID']);
        $i++;
    }

    CIBlock::clearIblockTagCache(22);

	$_SESSION['message'] = array('type' => 'success', 'text' => ' Цены удалены у '.$i.' товаров');
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