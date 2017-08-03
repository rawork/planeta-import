#!/usr/bin/env php
<?php

if (php_sapi_name() != 'cli') {
    die('Commandline mode only accepted'."\n");
}

$siteFolder = __DIR__ . '/../..';

$_SERVER["DOCUMENT_ROOT"] = $siteFolder;
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
define("LANG", "s1");

require($_SERVER["DOCUMENT_ROOT"]. "/bitrix/modules/main/include/prolog_before.php");

CModule::IncludeModule('currency');

$currencies = array('USD', 'EUR');
$log = __DIR__ . "/rates.log";

$DATE_RATE=date("d.m.Y");//сегодня
$QUERY_STR = "date_req=".$DB->FormatDate($DATE_RATE, CLang::GetDateFormat("SHORT", $lang), "D.M.Y");

//делаем запрос к www.cbr.ru с просьбой отдать курс на нынешнюю дату
$strQueryText = QueryGetData("www.cbr.ru", 80, "/scripts/XML_daily.asp", $QUERY_STR, $errno, $errstr);

//получаем XML и конвертируем в кодировку сайта
$charset = "UTF-8";
if (preg_match("/<"."\?XML[^>]{1,}encoding=[\"']([^>\"']{1,})[\"'][^>]{0,}\?".">/i", $strQueryText, $matches))
{
    $charset = Trim($matches[1]);
}
$strQueryText = eregi_replace("<!DOCTYPE[^>]{1,}>", "", $strQueryText);
$strQueryText = eregi_replace("<"."\?XML[^>]{1,}\?".">", "", $strQueryText);
$strQueryText = $APPLICATION->ConvertCharset($strQueryText, $charset, SITE_CHARSET);

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/classes/general/xml.php");

//парсим XML
$objXML = new CDataXML();
$res = $objXML->LoadString($strQueryText);
if($res !== false)
    $arData = $objXML->GetArray();
else
    $arData = false;

$NEW_RATE=Array();

//получаем курс нужной валюты $CURRENCY
foreach ($currencies as $CURRENCY) {
    if(!CCurrency::GetByID($CURRENCY))
        continue;

    if (is_array($arData) && count($arData["ValCurs"]["#"]["Valute"]) > 0) {
        for ($j1 = 0; $j1 < count($arData["ValCurs"]["#"]["Valute"]); $j1++) {
            if ($arData["ValCurs"]["#"]["Valute"][$j1]["#"]["CharCode"][0]["#"] == $CURRENCY) {
                $NEW_RATE['CURRENCY'] = $CURRENCY;
                $NEW_RATE['RATE_CNT'] = IntVal($arData["ValCurs"]["#"]["Valute"][$j1]["#"]["Nominal"][0]["#"]);
                $NEW_RATE['RATE'] = DoubleVal(str_replace(",", ".",
                    $arData["ValCurs"]["#"]["Valute"][$j1]["#"]["Value"][0]["#"])*1.02);
                $NEW_RATE['DATE_RATE'] = $DATE_RATE;
                break;
            }
        }
    }

    if ((isset($NEW_RATE['RATE'])) && (isset($NEW_RATE['RATE_CNT']))) {

        echo $NEW_RATE['CURRENCY'].' = '.$NEW_RATE['RATE']."\n";

        //курс получили, возможно, курс на нынешнюю дату уже есть на сайте, проверяем
        CModule::IncludeModule('currency');
        $arFilter = array(
            "CURRENCY" => $NEW_RATE['CURRENCY'],
            "DATE_RATE" => $NEW_RATE['DATE_RATE']
        );
        $by = "date";
        $order = "desc";

        $db_rate = CCurrencyRates::GetList($by, $order, $arFilter);
        if (!$ar_rate = $db_rate->Fetch()) //такого курса нет, создаём курс на нынешнюю дату
        {
            echo $NEW_RATE['CURRENCY'].' new rate added'."\n";
            error_log(date('Y-m-d H:i:s').' New rate added: '.$NEW_RATE['CURRENCY'].' = '.$NEW_RATE['RATE']."\n", 3, $log);
            CCurrencyRates::Add($NEW_RATE);
        }

    }
}