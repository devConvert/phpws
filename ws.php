<?php

/*

	to access phpws programmatically define the IS_PROGRAM const
	and include this file. this const can help determine if a
	method was called by an external script accessing phpws
	programmatically.
*/

if (!defined("IS_ALLOW"))
	define("IS_ALLOW", 1);

require_once __DIR__ . DIRECTORY_SEPARATOR . "config.php";
@include_once INCLUDES_DIR . DS . "config.php";

if (!defined("IS_PROGRAM")){	

	$uri = $_SERVER["REQUEST_URI"];

	if (isset($_SERVER["QUERY_STRING"]) && strlen($_SERVER["QUERY_STRING"]) > 0)
		$uri = str_replace("?" . $_SERVER["QUERY_STRING"], "", $uri);

	$route = explode("/", $uri);

	array_shift($route); // empty

	$routing_key = array_shift($route);

	if (array_key_exists($routing_key, PHPWSConfig::$routing)){
		$route = PHPWSConfig::$routing[$routing_key];
		$ws_ver = $route[0];
		$ctrl = $route[1];
		$method = $route[2];
	} else {
		$ws_ver = array_shift($route);

		if (count($route) < 2)
			die();

		$ctrl = s(array_shift($route));
		$method = strtolower(s(urldecode(array_shift($route))));
	}

	$ctrl_classname = get_ctrl_classname($ws_ver, $ctrl);

	$params = array();
	foreach ($route as $v)
		$params[] = s(urldecode($v));

	try {
		$reflectionMethod = new ReflectionMethod($ctrl_classname, $method);
		$return_val = $reflectionMethod->invokeArgs(new $ctrl_classname(), $params);
		$is_ws_error = false;
	} catch (Exception $e){
		$return_val = $e->getMessage();
		$is_ws_error = true;
	}

	header('Content-Type: application/json');

	if (!$is_ws_error){

		$result = array(
			"code" => 200,
			"value" => $return_val
		);

	} else {

		$result = array(
			"code" => 500,
			"value" => $return_val
		);

	}

	echo json_encode($result);
	die();
}