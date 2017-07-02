#!/usr/bin/env php
<?php

if (php_sapi_name() != 'cli') {
	die('Commandline mode only accepted'."\n");
}

set_time_limit(0);

//if (empty($argv[1])) {
//	die('First argument is required (site folder name)!'."\n");
//}

// test host value
$siteFolder = "/home/p/planeta27/public_html";
//$siteFolder = $argv[1];


$_SERVER["DOCUMENT_ROOT"] = $siteFolder;
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
define("LANG", "s1");

require($_SERVER["DOCUMENT_ROOT"]. "/bitrix/modules/main/include/prolog_before.php");
require ('src/helper.php');
$loader = require __DIR__.'/vendor/autoload.php';
CModule::IncludeModule('iblock');

chdir ( __DIR__ );

define('CURRENT_JSON', 'app/current.json');
define('STEP_ROWS', 10);
define('IBLOCK_ID', 22);

$current = false;
if (file_exists(CURRENT_JSON)) {
	$current = json_decode(file_get_contents(CURRENT_JSON), true);
	if ($current && isset($current['file']) && isset($current['position'])) {
		echo "Found current file\n";
	}
} else {
	$files = glob('upload/*.xlsx');
	if (!$files) {
		echo "No price .xlsx files\n";
		exit;
	}

	usort($files, function($a, $b){
		return filemtime($a) - filemtime($b);
	});

	$file = __DIR__ . '/' . array_shift($files);
	$pathinfo = pathinfo($file);
	$current = array(
		'file' => $file,
		'started' => date('Y-m-d_H-i'),
		'position' => 2,
		'log' => __DIR__ . "/app/log/log_$pathinfo[filename].log",
	);

	file_put_contents(CURRENT_JSON, json_encode($current));
	error_log("Start parse file $file at $current[started]", 3, $current['log']);
}

$pricelist = \PHPExcel_IOFactory::createReader('Excel2007');
$pricelist = $pricelist->load($current['file']);
$pricelist->setActiveSheetIndex(0);


// todo parse 1000 rows of file
$fileRows = $pricelist->getActiveSheet()->getHighestRow();
$lastPosition = $current['position'] + STEP_ROWS;
if ($fileRows < $lastPosition) {
	$lastPosition = $fileRows;
}

echo "rows $fileRows\n";

for ($i = $current['position']; $i <= $lastPosition; $i++) {
	echo "current row $i\n";
	$brand = trim($pricelist->getActiveSheet()->getCell('A'.$i)->getValue());
	$articul = trim($pricelist->getActiveSheet()->getCell('B'.$i)->getValue());
	$name = trim($pricelist->getActiveSheet()->getCell('C'.$i)->getValue());
	$gtin = trim($pricelist->getActiveSheet()->getCell('D'.$i)->getValue());
	$price = trim($pricelist->getActiveSheet()->getCell('E'.$i)->getValue());

	$artuculNum = preg_replace('(\s|-|.)' , '', $articul);
	$gtinNum = preg_replace('(\s|-|.)' , '', $gtin);

	error_log("Articuls: $artucul, $articulNum, $gtin, $gtinNum", 3, $current['log']);
	echo "Articuls: $artucul, $articulNum, $gtin, $gtinNum\n";

	// Find $elementId
	$arSelect = Array("ID", "IBLOCK_ID", "NAME", "DATE_ACTIVE_FROM","PROPERTY_ARTNUMBER");
	$arFilter = Array(
		"IBLOCK_ID"=>IBLOCK_ID,
		array(
			"LOGIC" => "OR",
			array("=PROPETY_ARTNUMBER" => $articul),
			array("=PROPETY_ARTNUMBER" => $articulNum),
			array("=PROPETY_ARTNUMBER" => $gtin),
			array("=PROPETY_ARTNUMBER" => $gtinNum),
		),
	);
	$res = CIBlockElement::GetList(Array(), $arFilter, false, Array("nPageSize"=>5), $arSelect);
	while ($ob = $res->GetNextElement()) {
		$arFields = $ob->GetFields();
		error_log("Found element $arFields[ID]", 3, $current['log']);
		echo "Found element $arFields[ID]\n";
		error_log($arFields['PROPERTY_ARTNUMBER_VALUE'], 3, $current['log']);
		echo "Fount articul $arFields[PROPERTY_ARTNUMBER_VALUE]\n";
//		$arProps = $ob->GetProperties();
//		error_log(serialize($arProps['PRO']), 3, $current['log']);

		// todo update price
//		$PRODUCT_ID = $arFields['ID'];
//		$PRICE_TYPE_ID = 1;
//
//		$arFields = Array(
//			"PRODUCT_ID" => $PRODUCT_ID,
//			"CATALOG_GROUP_ID" => $PRICE_TYPE_ID,
//			"PRICE" => $price,
//			"CURRENCY" => "RUB",
//			"QUANTITY_FROM" => 1,
//		);
//
//		// обновление цены
//		$res = CPrice::GetList(
//			array(),
//			array(
//				"PRODUCT_ID" => $PRODUCT_ID,
//				"CATALOG_GROUP_ID" => $PRICE_TYPE_ID
//			)
//		);
//
//		if ($arr = $res->Fetch())
//		{
//			CPrice::Update($arr["ID"], $arFields);
//		}
//		else
//		{
//			CPrice::Add($arFields);
//		}
	}

	$current['position']++;
	file_put_contents(CURRENT_JSON, json_encode($current));
}

if ($fileRows <= $current['position'] ) {
	error_log("Found $current[position] elements", 3, $current['log']);
	unlink($current['file']);
	unlink(CURRENT_JSON);

	// send email with info
}

echo "$current[file] - $current[position] - $current[started]\n";

require($_SERVER["DOCUMENT_ROOT"]. "/bitrix/modules/main/include/epilog_after.php");