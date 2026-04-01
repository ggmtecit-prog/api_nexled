<?php

require_once dirname(__FILE__) . "/config/tcpdf_config.php";


// Include the main TCPDF library (search the library on the following directories).
$tcpdf_include_dirs = array(
	realpath(dirname(__FILE__) . "/tcpdf.php"),	dirname(__FILE__) . "/tcpdf.php");
foreach ($tcpdf_include_dirs as $tcpdf_include_path) {
	if (@file_exists($tcpdf_include_path)) {
		require_once($tcpdf_include_path);
		break;
	}
}

//============================================================+
// END OF FILE
//============================================================+
