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
define('STEP_ROWS', 1000);
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
		'not_found' => 0,
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

	$articulNum = preg_replace('/(\s|-|\.)+/' , '', $articul);
	$gtinNum = preg_replace('/(\s|-|\.)+/' , '', $gtin);
	echo "Articuls: $articul, $articulNum, $gtin, $gtinNum\n";

	if (!$articul && !$articulNum && !$gtin && !$gtinNum) {
		echo "Empty articuls in row $i \n";
		continue;
	}

	$articulFilter = array(
		"LOGIC" => "OR"
	);
	if ($articul) {
		$articulFilter[] = array("PROPERTY_ARTNUMBER" => $articul);
		$articulFilter[] = array("NAME" => $articul);
	}

	if ($articulNum) {
		$articulFilter[] = array("PROPERTY_ARTNUMBER" => $articulNum);
		$articulFilter[] = array("NAME" => $articulNum);
	}

	if ($gtin) {
		$articulFilter[] = array("PROPERTY_ARTNUMBER" => $gtin);
		$articulFilter[] = array("NAME" => $gtin);
	}

	if ($gtinNum) {
		$articulFilter[] = array("PROPERTY_ARTNUMBER" => $gtinNum);
		$articulFilter[] = array("NAME" => $gtinNum);
	}

	// Find $elementId
	$arSelect = Array("ID", "IBLOCK_ID", "NAME", "DATE_ACTIVE_FROM","PROPERTY_ARTNUMBER");
	$arFilter = Array(
		"IBLOCK_ID" => IBLOCK_ID,
		$articulFilter,
	);
	$res = CIBlockElement::GetList(Array(), $arFilter, false, Array("nPageSize"=>5), $arSelect);
	if ($ob = $res->GetNextElement()) {
		$arFields = $ob->GetFields();
		error_log("Element $articul found", 3, $current['log']);
		echo "Element $articul found\n";

		// todo update price
		$PRODUCT_ID = $arFields['ID'];
		$PRICE_TYPE_ID = 1;

		$arFields = Array(
			"PRODUCT_ID" => $PRODUCT_ID,
			"CATALOG_GROUP_ID" => $PRICE_TYPE_ID,
			"PRICE" => $price,
			"CURRENCY" => "RUB",
			"QUANTITY_FROM" => 1,
		);

		// обновление цены
		$res = CPrice::GetList(
			array(),
			array(
				"PRODUCT_ID" => $PRODUCT_ID,
				"CATALOG_GROUP_ID" => $PRICE_TYPE_ID
			)
		);

		if ($arr = $res->Fetch())
		{
			echo "Update\n";
//			CPrice::Update($arr["ID"], $arFields);
		}
		else
		{
			echo "Add\n";
//			CPrice::Add($arFields);
		}
	} else {
		error_log("Element $articul not found", 3, $current['log']);
		$current['not_found']++;
		echo "Element $articul not found\n";
	}

	$current['position']++;
	file_put_contents(CURRENT_JSON, json_encode($current));
}

if ($fileRows <= $current['position'] ) {
	error_log("In price $current[position] elements", 3, $current['log']);
	unlink($current['file']);
	unlink(CURRENT_JSON);

	// todo send email summary, prices not found
}

echo "$current[file] - $current[position] - $current[started]\n";

require($_SERVER["DOCUMENT_ROOT"]. "/bitrix/modules/main/include/epilog_after.php");