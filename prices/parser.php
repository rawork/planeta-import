#!/usr/bin/env php
<?php

if (php_sapi_name() != 'cli') {
	die('Commandline mode only accepted'."\n");
}

set_time_limit(0);

// test host value
$siteFolder = __DIR__ . '/../..';

$_SERVER["DOCUMENT_ROOT"] = $siteFolder;
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
define("LANG", "s1");

require($_SERVER["DOCUMENT_ROOT"]. "/bitrix/modules/main/include/prolog_before.php");
require ('src/helper.php');
$loader = require __DIR__.'/vendor/autoload.php';
CModule::IncludeModule('iblock');
CModule::IncludeModule('catalog');

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

	$report = \PHPExcel_IOFactory::createReader('Excel2007');
	$report = $report->load($current['report']);
	$report->setActiveSheetIndex(0);
	$j = $report->getActiveSheet()->getHighestRow()+1;
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
		'report' => __DIR__ . "/reports/report_$pathinfo[filename].xlsx",
		'not_found' => 0,
	);

    unlink($current['log']);

	file_put_contents(CURRENT_JSON, json_encode($current));

	// Create new PHPExcel object
	$report = new \PHPExcel();

    // Set properties
	$report->getProperties()->setCreator("Roman Alyakritskiy");
	$report->getProperties()->setLastModifiedBy("Roman Alyakritskiy");
	$report->getProperties()->setTitle("Planeta27 price update report");

    // Add title header
	$report->setActiveSheetIndex(0);

	$report->getActiveSheet()->getColumnDimension('A')->setWidth(20);
	$report->getActiveSheet()->getColumnDimension('B')->setWidth(20);
	$report->getActiveSheet()->getColumnDimension('C')->setWidth(70);
	$report->getActiveSheet()->getColumnDimension('D')->setWidth(20);
	$report->getActiveSheet()->getColumnDimension('E')->setWidth(20);
	$report->getActiveSheet()->setCellValue('A1', 'Бренд');
	$report->getActiveSheet()->setCellValue('B1', 'Артикул');
	$report->getActiveSheet()->setCellValue('C1', 'Название');
	$report->getActiveSheet()->setCellValue('D1', 'Цена');
	$report->getActiveSheet()->setCellValue('E1', 'Статус');
	$j = 2;
	error_log("Start parse file $file at $current[started]\n", 3, $current['log']);
}

echo "$current[file] - $current[position] - $current[started] - start\n";

$pricelist = \PHPExcel_IOFactory::createReader('Excel2007');
$pricelist = $pricelist->load($current['file']);
$pricelist->setActiveSheetIndex(0);


// todo parse 1000 rows of file
$fileRows = $pricelist->getActiveSheet()->getHighestRow();
$lastPosition = $current['position'] + STEP_ROWS - ($current['position'] > 2 ? 1 : 0);
if ($fileRows <= $lastPosition) {
	$lastPosition = $fileRows;
}

echo "Total rows $fileRows\n";
error_log("Total rows $fileRows\n", 3, $current['log']);

for ($i = $current['position']; $i <= $lastPosition; $i++) {
	echo "current row $i : ";
	$brand = trim($pricelist->getActiveSheet()->getCell('A'.$i)->getValue());
	$articul = trim($pricelist->getActiveSheet()->getCell('B'.$i)->getValue());
	$name = trim($pricelist->getActiveSheet()->getCell('C'.$i)->getValue());
	$gtin = trim($pricelist->getActiveSheet()->getCell('D'.$i)->getValue());
	$priceRUB = trim($pricelist->getActiveSheet()->getCell('E'.$i)->getValue());
    $priceUSD = trim($pricelist->getActiveSheet()->getCell('F'.$i)->getValue());
    $priceEUR = trim($pricelist->getActiveSheet()->getCell('G'.$i)->getValue());

    $price = null;
    if ('' != trim($priceRUB)) {
        $price = $priceRUB;
        $currency = 'RUB';
    } elseif('' != trim($priceUSD)) {
        $price = $priceUSD;
        $currency = 'USD';
    } else {
        $price = $priceEUR;
        $currency = 'EUR';
    }

    // Если Цена не заполнена, переходим дальше
    if (empty($price)) {
        error_log("Element $articul - price not found\n", 3, $current['log']);
        $current['position']++;
        $j++;
        file_put_contents(CURRENT_JSON, json_encode($current));
        continue;
    }

	$articulNum = preg_replace('/(\s|-|\.)+/' , '', $articul);
	$gtinNum = preg_replace('/(\s|-|\.)+/' , '', $gtin);
	if (!$articul && !$articulNum && !$gtin && !$gtinNum) {
		echo "Empty articuls in row $i \n";
        $current['position']++;
        $j++;
        file_put_contents(CURRENT_JSON, json_encode($current));
		continue;
	}

    error_log("Filters: $articul $articulNum $gtin $gtinNum\n", 3, $current['log']);

	$articulFilter = array(
		"LOGIC" => "OR"
	);
	if ($articul) {
		$articulFilter[] = array("?PROPERTY_ARTNUMBER" => $articul);
		$articulFilter[] = array("?NAME" => $articul);
	}

	if ($articulNum) {
		$articulFilter[] = array("?PROPERTY_ARTNUMBER" => $articulNum);
		$articulFilter[] = array("?NAME" => $articulNum);
	}

	if ($gtin) {
		$articulFilter[] = array("?PROPERTY_ARTNUMBER" => $gtin);
		$articulFilter[] = array("?NAME" => $gtin);
	}

	if ($gtinNum) {
		$articulFilter[] = array("?PROPERTY_ARTNUMBER" => $gtinNum);
		$articulFilter[] = array("?NAME" => $gtinNum);
	}

	// Сначала ищем элемент по наличию артикула и gtin в Названии или в поле "Артикул"
	$arSelect = Array("ID", "IBLOCK_ID", "NAME", "DATE_ACTIVE_FROM","PROPERTY_ARTNUMBER");
	$arFilter = Array(
		"IBLOCK_ID" => IBLOCK_ID,
		$articulFilter,
	);
	$res = CIBlockElement::GetList(Array(), $arFilter, false, Array("nPageSize"=>5), $arSelect);

    $report->getActiveSheet()->setCellValue('A'.$j, $brand);
    $report->getActiveSheet()->setCellValue('B'.$j, $articul);
    $report->getActiveSheet()->setCellValue('C'.$j, $name);
    $report->getActiveSheet()->setCellValue('D'.$j, $price);

	if ($ob = $res->GetNextElement()) {
		$arFields = $ob->GetFields();
		error_log("Element $articul $gtin found\n", 3, $current['log']);
		echo "Element $articul $gtin found - ";

		// todo update price
		$PRODUCT_ID = $arFields['ID'];
		$PRICE_TYPE_ID = 1;

		$arPriceFields = Array(
			"PRODUCT_ID" => $PRODUCT_ID,
			"CATALOG_GROUP_ID" => $PRICE_TYPE_ID,
			"PRICE" => $price,
			"CURRENCY" => $currency,
		);

		// обновление цены для товара-элемента инфоблока
        CCatalogProduct::Add(array('ID' => $PRODUCT_ID));
        CPrice::SetBasePrice($PRODUCT_ID, $price, $currency);
        echo "Price set\n";


        $report->getActiveSheet()->setCellValue('E'.$j, 'Обновлено в товаре');

        // пробуем найти по артикулу и gtin в свойстве Артикул | Цена | Цвет (картинка)
        $articulFilter = array(
            "LOGIC" => "OR"
        );
        if ($articul) {
            $articulFilter[] = array("?PROPERTY_article_price" => $articul);
        }

        if ($articulNum) {
            $articulFilter[] = array("?PROPERTY_article_price" => $articulNum);
        }

        if ($gtin) {
            $articulFilter[] = array("?PROPERTY_article_price" => $gtin);
        }

        if ($gtinNum) {
            $articulFilter[] = array("?PROPERTY_article_price" => $gtinNum);
        }

        $arSelect = Array("ID", "IBLOCK_ID", "NAME", "DATE_ACTIVE_FROM","PROPERTY_ARTNUMBER", "PROPERTY_article_price");
        $arFilter = Array(
            "IBLOCK_ID" => IBLOCK_ID,
            $articulFilter,
        );
        $res = CIBlockElement::GetList(Array(), $arFilter, false, Array("nPageSize"=>5), $arSelect);

        // есть такой товар
        if ($ob = $res->GetNextElement()) {
            $arFields = $ob->GetFields();

            error_log("Element $articul $gtin found by article_price\n", 3, $current['log']);
            echo "Element $articul $gtin found by article_price - ";

            $PRODUCT_ID = $arFields['ID'];

            // Находим все значения свойства
            $articlePrices = array();
            $res = CIBlockElement::GetProperty(IBLOCK_ID, $PRODUCT_ID, "sort", "asc", array("CODE" => "article_price"));
            while ($ob = $res->GetNext()) {
                $articlePrices[] = $ob['VALUE'];
            }

            // Обновляем PROPERTY_article_price
            $arArticles = array();
            foreach ($articlePrices as $articlePrice) {
                $articlePriceArray = explode(' | ', $articlePrice);
                //error_log("{$articul} == {$articlePriceArray[0]} || {$gtin} == {$articlePriceArray[0]} => ".print_r($articul == $articlePriceArray[0] || $gtin == $articlePriceArray[0], true)."\n",3,$current['log']);
                if ($articul == $articlePriceArray[0] || $gtin == $articlePriceArray[0]){
                    $articlePriceArray[2] = CCurrencyRates::ConvertCurrency($price, $currency, "RUB");
                    $arArticles[] = array("VALUE" => implode(' | ', $articlePriceArray));
                } else {
                    $arArticles[] = array("VALUE" => $articlePrice);
                }
            }

            error_log(print_r($arArticles, true), 3, $current['log']);

            CIBlockElement::SetPropertyValuesEx($PRODUCT_ID, false, array("article_price" => $arArticles));
            echo "Article price updated\n";
        }
	} else {

	    // пробуем найти по артикулу и gtin в свойстве Артикул | Цена | Цвет (картинка)
        $articulFilter = array(
            "LOGIC" => "OR"
        );
        if ($articul) {
            $articulFilter[] = array("?PROPERTY_article_price" => $articul);
        }

        if ($articulNum) {
            $articulFilter[] = array("?PROPERTY_article_price" => $articulNum);
        }

        if ($gtin) {
            $articulFilter[] = array("?PROPERTY_article_price" => $gtin);
        }

        if ($gtinNum) {
            $articulFilter[] = array("?PROPERTY_article_price" => $gtinNum);
        }

        $arSelect = Array("ID", "IBLOCK_ID", "NAME", "DATE_ACTIVE_FROM","PROPERTY_ARTNUMBER", "PROPERTY_article_price");
        $arFilter = Array(
            "IBLOCK_ID" => IBLOCK_ID,
            $articulFilter,
        );
        $res = CIBlockElement::GetList(Array(), $arFilter, false, Array("nPageSize"=>5), $arSelect);

        if ($ob = $res->GetNextElement()) {
            $arFields = $ob->GetFields();

            error_log("Element $articul $gtin found by article_price\n", 3, $current['log']);
            echo "Element $articul $gtin found by article_price - ";

            $PRODUCT_ID = $arFields['ID'];

            // Находим все значения свойства
            $articlePrices = array();
            $res = CIBlockElement::GetProperty(IBLOCK_ID, $PRODUCT_ID, "sort", "asc", array("CODE" => "article_price"));
            while ($ob = $res->GetNext()) {
                $articlePrices[] = $ob['VALUE'];
            }

            // Обновляем PROPERTY_article_price
            $arArticles = array();
            foreach ($articlePrices as $articlePrice) {
                $articlePriceArray = explode(' | ', $articlePrice);
//                error_log("{$articul} == {$articlePriceArray[0]} || {$gtin} == {$articlePriceArray[0]} => ".print_r($articul == $articlePriceArray[0] || $gtin == $articlePriceArray[0], true)."\n",3,$current['log']);
                if ($articul == $articlePriceArray[0] || $gtin == $articlePriceArray[0]){
                    $articlePriceArray[2] = CCurrencyRates::ConvertCurrency($price, $currency, "RUB");
                    $arArticles[] = array("VALUE" => implode(' | ', $articlePriceArray));
                } else {
                    $arArticles[] = array("VALUE" => $articlePrice);
                }
            }

            error_log(print_r($arArticles, true), 3, $current['log']);

            CIBlockElement::SetPropertyValuesEx($PRODUCT_ID, false, array("article_price" => $arArticles));
            echo "Article price updated\n";
            $report->getActiveSheet()->setCellValue('E'.$j, 'Обновлено по доп артикулу');
        } else {
            error_log("Element $articul $gtin not found\n", 3, $current['log']);
            echo "Element $articul $gtin not found\n";
            $current['not_found']++;

            $report->getActiveSheet()->setCellValue('E'.$j, 'Товар не найден');
        }
	}

    CIBlock::clearIblockTagCache(IBLOCK_ID);

	$current['position']++;
	$j++;
	file_put_contents(CURRENT_JSON, json_encode($current));
}

$objWriter = new \PHPExcel_Writer_Excel2007($report);
$objWriter->save($current['report']);



if ($fileRows <= $current['position'] ) {

    $reportLink = "http://planeta27.ru" . str_replace(realpath($DOCUMENT_ROOT), '', $current['report']);

    rename($current['file'], $current['file'].'.done');
    unlink(CURRENT_JSON);

    // todo send email summary, prices not found
    $pathinfo = pathinfo($current['file']);
    $filename = $pathinfo['basename'];
    $count = $fileRows - 2;
    error_log("In price $count elements\n", 3, $current['log']);
    error_log("Report " . $reportLink, 3, $current['log']);
    $res = mail('rawork@yandex.ru', "Pricelist $filename parsed", "
Информация о результатах:

Всего цен: $count;
Не найдено по артикулу и штрих-коду: $current[not_found]
Отчет: $reportLink
    ");

    $arEventFields = array(
        "PRICE_NAME" => $filename,
        "OVERVIEW" => "
Информация о результатах:

Всего цен: $count;
Не найдено по артикулу и штрих-коду: $current[not_found]
Отчет: $reportLink
    ");
    CEvent::SendImmediate("PRICE_FILE_UPDATED", "ru", $arEventFields);
}
echo "$current[file] - $current[position] - $current[started] - end \n";

require($_SERVER["DOCUMENT_ROOT"]. "/bitrix/modules/main/include/epilog_after.php");