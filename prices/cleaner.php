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

    var_dump($brands);
    die;

	$_SESSION['message'] = array('type' => 'success', 'text' => ' Цены удалены');
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