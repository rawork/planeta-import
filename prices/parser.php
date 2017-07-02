<?php

require_once('vendor/autoload.php');

if (php_sapi_name() != 'cli') {
    die('Commandline mode only accepted'."\n");
}



if (file_exists(GOOGLE_CATEGORIES_XSLX_PATH)) {
	$objPHPExcel = \PHPExcel_IOFactory::createReader('Excel2007');
	$objPHPExcel = $objPHPExcel->load(GOOGLE_CATEGORIES_XSLX_PATH);
	$objPHPExcel->setActiveSheetIndex(0);
	$i = $objPHPExcel->getActiveSheet()->getHighestRow()+1;
}

