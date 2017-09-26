<?php

/*
	CLI access point
*/

define("IS_PROGRAM", 1);
define("IS_ALLOW", 1);

if (isset($_SERVER["argv"]) && is_array($_SERVER["argv"]) && isset($_SERVER["argv"][1]) && isset($_SERVER["argv"][2]) && isset($_SERVER["argv"][3])){
	$ws_ver = $_SERVER["argv"][1];
	$ctrl = $_SERVER["argv"][2];
	$method = $_SERVER["argv"][3];
} else {
	die("usage: run.php [ws_ver] [ctrl] [method] {[is_verbose]}\ne.g. run.php 1 base ping");
}

if (isset($_SERVER["argv"][4]) && intval($_SERVER["argv"][4]) == 1 )
	$is_verbose = true;
else
	$is_verbose = false;

//$_SERVER["REQUEST_URI"] = "/ws/".$ws_ver."/".$ctrl."/".$method;
//$_SERVER["QUERY_STRING"] = "";

require_once "ws.php";

$ctrl_classname = get_ctrl_classname($ws_ver, $ctrl);

try {
	$reflectionMethod = new ReflectionMethod($ctrl_classname, $method);
	$output = $reflectionMethod->invoke(new $ctrl_classname());
} catch (Exception $e){
	$output = "Exception: " . $e->getMessage();
}

if ($is_verbose) {
	if (is_array($output))
		print_r($output);
	else
		echo $output;
}