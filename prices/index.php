<?php

session_start();
require_once ('src/helper.php');

if ('POST' == $_SERVER['REQUEST_METHOD']) {
	if (empty($_FILES['file_new'])) {
		$_SESSION['message'] = array('type' => 'danger', 'text' => 'Empty file');
		header('location: '. $_SERVER['REQUEST_URI']);
		exit;
	}

	if ('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' != $_FILES['file_new']['type']) {
		$_SESSION['message'] = array('type' => 'danger', 'text' => 'Wrong file type (needed .xlsx)');
		header('location: '. $_SERVER['REQUEST_URI']);
		exit;
	}

	$name = nextName(translit($_FILES['file_new']['name']), __DIR__ . '/upload');
	move_uploaded_file($_FILES['file_new']['tmp_name'], $name);

	$_SESSION['message'] = array('type' => 'success', 'text' => 'File uploaded');
	header('location: '. $_SERVER['REQUEST_URI']);
	exit;
}


$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
unset($_SESSION['message']);

require(__DIR__. "/../../bitrix/modules/main/include/prolog_before.php");
global $USER;

$title = 'Загрузка прайслистов для обновления цен в каталоге';

include('src/view/header.view.php');

include('src/view/uploadform.view.php');

include('src/view/footer.view.php');

require(__DIR__. "/../../bitrix/modules/main/include/epilog_after.php");