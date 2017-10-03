<?php

session_start();
require_once ('src/helper.php');

if ('POST' == $_SERVER['REQUEST_METHOD']) {


	$_SESSION['message'] = array('type' => 'success', 'text' => 'File uploaded');
	header('location: '. $_SERVER['REQUEST_URI']);
	exit;
}


$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
unset($_SESSION['message']);

require(__DIR__. "/../../bitrix/modules/main/include/prolog_before.php");
global $USER;

$title = 'Удаление цен в каталоге';

include('src/view/header.view.php');

include('src/view/cleaner.view.php');

include('src/view/footer.view.php');

require(__DIR__. "/../../bitrix/modules/main/include/epilog_after.php");