<?php

if (!defined("IS_ALLOW"))
	die();

date_default_timezone_set('UTC');

if (!defined("DS"))
	define("DS", DIRECTORY_SEPARATOR);

define("MICROSERVICE_ROOT", __DIR__);
define("INCLUDES_DIR", MICROSERVICE_ROOT . DS . "includes");
define("LIBS_DIR", MICROSERVICE_ROOT . DS . "libs");



class PHPWSConfig {
	/*
		phpws admin credentials
	*/
	public static $admin_username = "phpwsadmin";
	public static $admin_password = "admin";

	// by default it is not allowed to call directly the base controller (only by routing)
	public static $is_allow_direct_base_ctrl_call = false;

	/*
		$routing = array(
			"log" => array("1", "main", "log")
		);

		means localhost/log is mapped to MainControllerV1/log

		all routings must be enabled in nginx as well. by default
		/log, /mail and /admin are already enabled.
	*/
	public static $routing = array(
		"log" => array("1", "base", "routing_stub"),
		"mail" => array("1", "base", "routing_stub"),
		"admin" => array("1", "base", "routing_stub"),
		"ping" => array("1", "base", "ping")
	);

	/*
		$db_connections = array(
			"reader" => array(
				"host" => "localhost",
				"user" => "root",
				"pass" => "",
				"db_name" => "main",
				"port" => 3306
			)
		);
	*/
	public static $db_connections = array(
		"file_logger" => array(
			"host" => "localhost",
			"user" => "root",					// must have privileges to create db tables with indices, should be restricted only to localhost
			"pass" => "ot-mysql-default-pass",	// same as in scripts/install_mysql.sh
			"db_name" => "file_logger",
			"port" => 3306
		)
	);

	/*
		log topic for saving email sending stats
	*/
	public static $logger_emails_log_topic = "emails";

	/*
		log server hostname
	*/
	public static $logger_host = "localhost";

	/*
		log server relative path for sending logs
	*/
	public static $logger_host_rel_path = "/log";

	/*
		logs topics basedir
		e.g.: /logs/0/topics
	*/
	public static $logs_basedir_vols = array();

	/*
		is every received log should be saved to file automatically?
		even if set to FALSE it is still possible to save log to 
		file when a subscribed event handler returns TRUE. If the
		event handler returns FALSE then the log will not be saved
		to file even if PHPWSConfig::$is_auto_save_log_to_file is set
		to TRUE.

		as long as PHPWSConfig::$logs_basedir_vols is an empty array
		there's no point in setting PHPWSConfig::$is_auto_save_log_to_file to TRUE.
	*/
	public static $is_auto_save_log_to_file = false;

	/*
		defaults for the rolling_file_logger
	*/
	public static $default_rolling_file_logger_line_delimiter = "\u{0005}"; //chr(5);
	public static $default_rolling_file_logger_col_delimiter = "\u{0006}";	//chr(6);
	public static $default_rolling_file_logger_file_extension = "log";
	public static $default_rolling_file_logger_dir_dt_format = "Y-m-d";
	public static $default_rolling_file_logger_file_dt_format = "H";
	public static $default_rolling_file_logger_line_dt_format = "i:s";

	/*
		$subscriptions = array(
			"topic1" => array("1", "main", "topic1_handler")
		);

		means that the method "topic1_handler" is subscribed
		to any received log with the topic "topic1"
	*/
	public static $subscriptions = array();

	/*
		AWS configurations

		example:

		"region" => "us-east-1",
		"ses_version" => "2010-12-01",
		"arns" => array(
			"info@yourdomain.com" => "arn:aws:ses:us-east-1:542479765460:identity/info@yourdomain.com"
		)
	*/
	public static $AWS = array(
		"key" => "",
		"secret" => "",
		"region" => "",
		"ses_version" => "",
		"default_source" => "",
		"default_return_path" => "",

		// list of all AWS Email identity ARNs
		"arns" => array()
	);
}

$search_array = ["\"", "'", "<", ">", "\0", "\b", "\r", "\t", "\Z", "\\", "\x00", "\n", "\x1a"];
if (!function_exists("s")){
	function s($input){
		global $search_array;
		return str_replace($search_array, "", $input);
	}
}

if (!function_exists("get_ctrl_classname")){
	function get_ctrl_classname($ws_ver, $ctrl){
		$ctrl = strtolower($ctrl);
		$ctrl_classname = ucfirst($ctrl) . "ControllerV" . $ws_ver;
		require_once MICROSERVICE_ROOT . DS . "BaseControllerV" . $ws_ver . ".php";

		if ($ctrl != "base")
			@include_once INCLUDES_DIR . DS . $ctrl_classname . ".php";

		return $ctrl_classname;
	}
}