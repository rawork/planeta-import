#!/usr/bin/env php
<?php

$loader = require __DIR__.'/vendor/autoload.php';

define('CURRENT_JSON', 'app/current.json');

if (php_sapi_name() != 'cli') {
    die('Commandline mode only accepted'."\n");
}

$current = false;
if (file_exists(CURRENT_JSON)) {
	$current = json_decode(file_get_contents(CURRENT_JSON),true);
}

if ($current && isset($current['file']) && isset($current['position'])) {
	$file = $current['file'];
	$position = $current['position'];

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

	$position = 2;
}

//	$objPHPExcel = \PHPExcel_IOFactory::createReader('Excel2007');
//	$objPHPExcel = $objPHPExcel->load($file);
//	$objPHPExcel->setActiveSheetIndex(0);


echo "{$file} - {$position}\n";






