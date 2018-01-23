<?php

if (!defined("IS_ALLOW"))
	die();

use Aws\Ses\SesClient;

class BaseControllerV1
{
	protected $ip = "";
	protected $ip_long = "";
	protected $geoip = "";
	protected $country_code = "";
	protected $mobile_detect = "";
	protected $is_aws_api_included = false;

	// logging
	protected $logs_basedir_vol_id_default = 0;
	protected $evt = "";



	function __construct() {}

	function __call($method, $args){
		die();
	}

	protected function is_admin(){

		if (isset($_GET["user"]) && isset($_GET["pass"]) && $_GET["user"] === PHPWSConfig::$admin_username && $_GET["pass"] === PHPWSConfig::$admin_password)
			return true;

		return false;
	}

	protected function db_connect($db_conn_name){
		require_once LIBS_DIR . DS . "mysql.php";

		$db = new DB(PHPWSConfig::$db_connections);

		return $db->connect($db_conn_name);	// returns Queryable
	}

	protected function include_aws_api(){
		if ($this->is_aws_api_included)
			return;

		require_once LIBS_DIR . DS . "aws_api_v3" . DS . "aws-autoloader.php";

		$this->is_aws_api_included = true;
	}

	protected function get_empty_email_config($source = "", $returnPath = ""){
		if ($source == "")
			$source = PHPWSConfig::$AWS["default_source"];

		if ($returnPath == "")
			$returnPath = PHPWSConfig::$AWS["default_return_path"];

		return array(
			"subject" => "",
			"body" => "",
			"bodyText" => "",
			"to" => array(),
			"cc" => array(),
			"bcc" => array(),
			"replyTo" => array(),
			"source" => $source,
			"returnPath" => $returnPath
		);
	}

	protected function get_request_ip(){
		if ($this->ip != "")
			return $this->ip;

		$ip = !empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];
		$ip_arr = explode(",", $ip);
		$this->ip = $ip_arr[0];

		return $this->ip;
	}

	protected function get_request_ip_long(){
		if ($this->ip_long != "")
			return $this->ip_long;

		$ip_long = ip2long($this->get_request_ip());
		if ($ip_long === false)
			$this->ip_long = 0;

		return $this->ip_long;
	}

	protected function get_request_country_code(){
		if ($this->country_code != "")
			return $this->country_code;

		if ($this->geoip == ""){
			if (!function_exists("geoip_country_code_by_addr"))
				include_once LIBS_DIR . DS . "geoip.php";

			$this->geoip = geoip_open(LIBS_DIR . DS . "geoip.dat", GEOIP_MEMORY_CACHE);
		}

		$this->country_code = geoip_country_code_by_addr($this->geoip, $this->get_request_ip());
		
		return $this->country_code;
	}

	protected function get_mobile_detect(){
		if ($this->mobile_detect == ""){
			if (!class_exists("Mobile_Detect"))
				include_once LIBS_DIR . DS . "mobile_detect.php";

			$this->mobile_detect = new Mobile_Detect;
		}

		return $this->mobile_detect;
	}

	protected function sanitize($str){
		return s($str);
	}

	protected function aws_mail($emailConfig, $isLog = true){
		$reason = "";

		if (array_key_exists($emailConfig["source"], PHPWSConfig::$AWS["arns"]) && array_key_exists($emailConfig["returnPath"], PHPWSConfig::$AWS["arns"])){

			$is_sent = true;

			$this->include_aws_api();

			try {
				$email_client = SesClient::factory(array(
					'credentials' => array(
						'key'    => PHPWSConfig::$AWS["key"],
						'secret' => PHPWSConfig::$AWS["secret"],
					),
					'region' => PHPWSConfig::$AWS["region"],
					"version" => PHPWSConfig::$AWS["ses_version"],
					"http" => [ "verify" => false ]
				));

				$emailPayload = array(
					'Destination' => array(
						'ToAddresses' => $emailConfig["to"],
						'CcAddresses' => $emailConfig["cc"],
						'BccAddresses' => $emailConfig["bcc"]
					),
					'Message' => array(
						'Subject' => array(
							'Data' => $emailConfig["subject"],
							'Charset' => 'utf8'
						),
						'Body' => array(
							'Text' => array(
								'Data' => $emailConfig["bodyText"],
								'Charset' => 'utf8'
							),
							'Html' => array(
								'Data' => $emailConfig["body"],
								'Charset' => 'utf8'
							)
						)
					),
					'ReplyToAddresses' => $emailConfig["replyTo"], 

					'Source' => $emailConfig["source"], 
					'SourceArn' => PHPWSConfig::$AWS["arns"][$emailConfig["source"]], 

					'ReturnPath' => $emailConfig["returnPath"],	
					'ReturnPathArn' => PHPWSConfig::$AWS["arns"][$emailConfig["returnPath"]] 
				);

				$email_client->sendEmail($emailPayload);
			}
			catch(Exception $e){
				$reason = $e->getMessage();
				$is_sent = false;
			}
		} else {
			$is_sent = false;
			$reason = "ARN not found";
		}

		if ($isLog)
			$this->rolling_file_logger(PHPWSConfig::$logger_emails_log_topic, array(($is_sent ? "1" : "0"), json_encode($emailConfig), $reason));

		return $is_sent;
	}

	protected function http_mail(){
		if (!$this->is_admin())
			throw new Exception("must be an admin");

		// override this method to enable a default http mailer

		return true;
	}

	public function ping(){
		return "pong";
	}
	
	public function routing_stub(){
		die("This routing points to a stub. In order to use this endpoint please extend the base controller and change the routing.");
	}

	
	/* Logging Methods */

	protected function rolling_file_logger($topic = "", $data = "", $headers = "", $remark = "", $logs_basedir_vol_id = "", $mysql_data_types = "", $colDelimiter = "", $lineDelimiter = "", $fileExtenstion = "", $lineDateFormat = "", $dirNameDateFormat = "", $fileNameDateFormat = ""){
		if ($data == "" || $topic == "")
			return false;

		if ($logs_basedir_vol_id == "")
			$logs_basedir_vol_id = $this->logs_basedir_vol_id_default;

		if (!array_key_exists($logs_basedir_vol_id, PHPWSConfig::$logs_basedir_vols))
			return false;

		if ($colDelimiter == "")
			$colDelimiter = PHPWSConfig::$default_rolling_file_logger_col_delimiter;

		if ($lineDelimiter == "")
			$lineDelimiter = PHPWSConfig::$default_rolling_file_logger_line_delimiter;

		if ($fileExtenstion == "")
			$fileExtenstion = PHPWSConfig::$default_rolling_file_logger_file_extension;

		if ($lineDateFormat == "")
			$lineDateFormat = PHPWSConfig::$default_rolling_file_logger_line_dt_format;

		if ($dirNameDateFormat == "")
			$dirNameDateFormat = PHPWSConfig::$default_rolling_file_logger_dir_dt_format;

		if ($fileNameDateFormat == "")
			$fileNameDateFormat = PHPWSConfig::$default_rolling_file_logger_file_dt_format;

		if (!is_array($data))
			$data = array($data);

		$rows = array();
		$numRows = count($data);
		for ($i=0; $i<$numRows; $i++){
			if (!is_array($data[$i]))
				$row = array($data[$i]);
			else
				$row = $data[$i];

			if (count($row) == 0)
				continue;

			if (count($row) == 1 && strlen($row[0]) == 0)
				continue;

			$line = implode($colDelimiter, $row);

			if (strlen($line) > 0 && $lineDateFormat != "")
				$line = date($lineDateFormat) . $colDelimiter . $line;

			$rows[] = $line;
		}

		// first character is always $lineDelimiter
		$data_str = $lineDelimiter . implode($lineDelimiter, $rows);
		
		$basedir = PHPWSConfig::$logs_basedir_vols[$logs_basedir_vol_id] . DS . $topic;
		$high = date($dirNameDateFormat) . "";
		$low = date($fileNameDateFormat) . "." . $fileExtenstion;
		
		if (@file_put_contents($basedir . DS . $high . DS . $low, $data_str, FILE_APPEND) === false){
			// $basedir always exists so $high dir may not, $low file will be created automatically if not exists

			if (!file_exists($basedir))
				@mkdir($basedir);

			if (!file_exists($basedir . DS . $high))
				@mkdir($basedir . DS . $high);

			if (!file_exists($basedir . DS . "meta.config")){
				$meta = json_encode(array(
					"colDelimeter" => $colDelimiter,
					"lineDelimeter" => $lineDelimiter,
					"fileExtension" => $fileExtenstion,
					"lineDateFormat" => $lineDateFormat,
					"dirNameDateFormat" => $dirNameDateFormat,
					"fileNameDateFormat" => $fileNameDateFormat,
					"headers" => $headers,
					"mysql_data_types" => $mysql_data_types,
					"remark" => $remark
				));

				@file_put_contents($basedir . DS . "meta.config", $meta);
			}

			if (@file_put_contents($basedir . DS . $high . DS . $low, $data_str, FILE_APPEND) === false)
				return false;
		}

		return true;
	}

	protected function save_log_locally($db_conn_name = "", $topic = "", $data = "", $headers = "", $mysql_data_types = "", $remark = ""){

		if ($db_conn_name == "" || $topic == "" || $data == "")
			return false;

		$uid = mt_rand(0, mt_getrandmax());
		$dt = date('Y-m-d H:i:s');

		$evt = array(
			$topic,
			$data,
			$dt,
			$uid
		);

		if ($headers != "")
			$evt[4] = $headers;

		if ($mysql_data_types != ""){
			
			if (!isset($evt[4]))
				$evt[4] = "";

			$evt[5] = $mysql_data_types;
		}

		if ($remark != ""){

			if (!isset($evt[4]))
				$evt[4] = "";

			if (!isset($evt[5]))
				$evt[5] = "";
			
			$evt[6] = $remark;
		}

		$evt_b64 = base64_encode(json_encode($evt));

		$db = $this->db_connect($db_conn_name);

		$sql = "insert into events_queue (dt, uid, evt) values ('".$dt."', ".$uid.", '".$evt_b64."')";

		$db->query($sql);

		return true;
	}

	protected function send_local_logs_batch_to_server($db_conn_name = ""){
		/*
			Query the local mysql table events_queue for logs and send as batch to the log server
			then remove logs which were received successfully from the local mysql table
		*/

		if ($db_conn_name == "")
			return false;

		$db = $this->db_connect($db_conn_name);

		$sql = array();

		$sql[] = "select dt, uid, evt from events_queue order by dt asc limit 100";

		$results = $db->query($sql);
		$count_results = count($results[0]);

		$evts = "[";
		$add_comma = "";

		foreach ($results[0] as $k => $v){
			$evts .= $add_comma . base64_decode($results[0][$k]["evt"]);
			$add_comma = ",";
		}

		$evts .= "]";

		$log_result = $this->send_logs_to_server($evts);

		if (!is_array($log_result)){
			if (intval($log_result) == 0){
				// all evts failed so don't delete them from table

				return false;
			} elseif ($count_results > 0) {
				// all evts ok so delete them from table

				$first_dt = $results[0][0]["dt"];

				$last_dt = $results[0][$count_results-1]["dt"];

				$sql = "delete from events_queue where ";
				$add_or = "";
				$c = 0;

				for ($i=0; $i<$count_results; $i++){
					$evt = $results[0][$i];
					
					if ($evt["dt"] == $first_dt || $evt["dt"] == $last_dt){
						$sql .= $add_or . " (dt = '" . $evt["dt"] . "' and uid = " . $evt["uid"] . ") ";
						$add_or = " or ";
						$c++;
					}

					if ($c >= 10){
						$db->query($sql);

						$sql = "delete from events_queue where ";
						$add_or = "";
						$c = 0;
					}
					
				}

				if ($c > 0)
					$sql = array($sql);
				else
					$sql = array();

				if ($first_dt != $last_dt)
					$sql[] = "delete from events_queue where dt > '".$first_dt."' and dt < '" . $last_dt . "'";

				$db->query($sql);

				return true;
			}
		}

		// not all evts were ok so delete only ok ones from table

		$fails = array();

		foreach ($log_result as $k => $v)
			$fails[$log_result[$k][0] . "_" . $log_result[$k][1]] = false;		// $fails[$dt . "_" . $uid] => false

		$sql = "delete from events_queue where ";
		$add_or = "";
		$c = 0;

		for ($i=0; $i<$count_results; $i++){
			$evt = $results[0][$i];
			
			if (!array_key_exists($evt["dt"] . "_" . $evt["uid"], $fails)){
				// evt success then delete it
				$sql .= $add_or . " ( dt = '" . $evt["dt"] . "' and uid = " . $evt["uid"] . ") ";
				$add_or = " or ";
				$c++;

				if ($c >= 10){
					$db->query($sql);

					$sql = "delete from events_queue where ";
					$add_or = "";
					$c = 0;
				}
			}

		}

		if ($c > 0)
			$db->query($sql);

		return $log_result;
	}

	protected function send_logs_to_server($evts_json){
		/*
			A simple send and forget to the log server

			Expecting $evts_json to be a json array holding some evts:
			[
					[
						0					=> topic, string
						1					=> data, array of arrays, each internal array is a row
						2					=> uid, will be returned in case of log fail (not saved to actual log)
						3					=> dt, will be returned in case of log fail (not saved to actual log)
						4					=> headers, optional, array of strings
						5					=> remark, optional, string
						6					=> logs_basedir_vol_id, optional, int
					]
			]
		
			$evts_json can also be a single evt (in json):
			[
				0 => ...
				...
			]
		*/

		if ($evts_json == "")
			return false;

		$host = PHPWSConfig::$logger_host;
		$path = PHPWSConfig::$logger_host_rel_path;
		$port = 80;

		$data = array (
			"evts" => $evts_json
		);

		$encoded_data = "";

		foreach($data as $key => $value)
			$encoded_data .= $key . '=' . urlencode(stripslashes($value)).'&';

		$encoded_data = substr($encoded_data, 0, strlen($encoded_data) - 1);

		$http_request  = "POST $path HTTP/1.0\r\n";
		$http_request .= "Host: $host\r\n";
		$http_request .= "Content-Type: application/x-www-form-urlencoded;\r\n";
		$http_request .= "Content-Length: " . strlen($encoded_data) . "\r\n";
		//$http_request .= "User-Agent: reCAPTCHA/PHP\r\n";
		$http_request .= "\r\n";
		$http_request .= $encoded_data;

		$response = '';

		if( false == ( $fs = @fsockopen($host, $port, $errno, $errstr, 10) ) ){
		
			$result = 0;

		} else {

			fwrite($fs, $http_request);

			while (!feof($fs))
				$response .= fgets($fs, 1160); // One TCP-IP packet
			fclose($fs);
			$response = explode("\r\n\r\n", $response, 2);

			/*
			echo "<pre>";
			print_r($response);
			die();
		
			Array
			(
				[0] =>	HTTP/1.1 200 OK
						Content-Type: application/json
						Server: Microsoft-IIS/10.0
						X-Powered-By: PHP/7.0.9
						Date: Tue, 14 Mar 2017 14:36:49 GMT
						Connection: close
						Content-Length: 25
				[1] =>	{"code":200,"value":true}
			)
			*/
		
			$response = $response[1];
			$response = json_decode($response, true);

			$result = json_decode($response["value"], true);
		}

		return $result;
	}

	protected function http_receive_logs(){
		/*
			see test/receive_logs.htm for a detailed example

			Expected parameter $_POST["evt"] containing a json array:
				0					=> topic, string
				1					=> data, array of arrays, each internal array is a row (e.g. [["A","B","C"]])
				2					=> uid, a unique id for each evt which will be returned in case of log fail (not saved to actual log)
				3					=> dt, will be returned in case of log fail (not saved to actual log)
				4					=> headers, optional, array of strings (e.g. ["h1","h2","h3"])
				5					=> mysql data types, optional, arrays of strings (e.g. ["int","varchar(20)","decimal"])
				6					=> remark, optional, string
				7					=> logs_basedir_vol_id, optional, number
				

			OR

			$_POST["evts"] = json array of what $this->log() is expecting to receive
		*/
		
		if (isset($_POST["evt"]))
			$this->evt = json_decode($_POST["evt"], true);

		if ($this->evt != ""){

			// single log

			$evt = $this->evt;

			$topic = $this->sanitize($evt[0]);
			
			$data = array($evt[1]);

			$headers = "";
			if (isset($evt[4]))
				$headers = $evt[4];		// should be a string containing a json array

			$mysql_data_types = "";
			if (isset($evt[5]))
				$mysql_data_types = $evt[5];	// should be a string containing a json array

			$remark = "";
			if (isset($evt[6]))
				$remark = $evt[6];		// should be a string

			if (isset($evt[7]))
				$logs_basedir_vol_id = intval($evt[7]);
			else
				$logs_basedir_vol_id = "";

			$is_save_to_file = PHPWSConfig::$is_auto_save_log_to_file;

			// check for subscriptions
			if (array_key_exists($topic, PHPWSConfig::$subscriptions)){
				die($topic);
				$sub = PHPWSConfig::$subscriptions[$topic];
				$ctrl_classname = get_ctrl_classname($sub[0], $sub[1]);

				try {
					echo "<pre>"; print_r($data);
					$reflectionMethod = new ReflectionMethod($ctrl_classname, $sub[2]);
					$is_save_to_file_local = $reflectionMethod->invokeArgs(new $ctrl_classname(), $data);

					if ($is_save_to_file_local === false)
						$is_save_to_file = false;
					elseif ($is_save_to_file_local === true)
						$is_save_to_file = true;

				} catch (Exception $ex){
					die("123");
					$this->rolling_file_logger("phpws_errors", array(array($ex->getMessage())));
				}

				die("444");
			}

			if ($is_save_to_file)
				return $this->rolling_file_logger($topic, $data, $headers, $remark, $logs_basedir_vol_id, $mysql_data_types) ? 1 : 0;

			return 1;

		} elseif (isset($_POST["evts"])) {
			
			// multiple logs

			if (strlen($_POST["evts"]) == 0)
				return 0;

			$evts = json_decode($_POST["evts"], true);

			if (!is_array($evts))
				$evts = array($evts);

			$fails = array();

			$is_all_ok = true;

			foreach ($evts as $k => $v){
				$evt = $evts[$k];

				$this->evt = $evt;

				if ($this->http_receive_logs() == 0){
					// log failed
					$fails[] = array($evt[2], $evt[3]);
					$is_all_ok = false;
				}
			}

			if ($is_all_ok)
				return 1;

			if (count($fails) == count($evts))
				return 0;

			return json_encode($fails);
		}

		return 0;
	}

	protected function file_logger_query($sql = ""){
		/*
			requires a connection to mysql

			$mysql_data_types contains an associative array:
			[
				"col1" => "mysql data type"
				...
			]

			e.g.:
			[
				"col1" => "int",
				"col2" => "varchar(20)"
			]
		*/

		// sql variables
		$select = array();					// contains ONLY the header names without the sql functions (every cell contains a single col and not an array like it should as some functions take more than one col)
		$select_funcs = array();			// contains ONLY the sql functions to be executed on the header value, including aggregative functions: select_id => func_id. THERE MUST BE A func_id FOR EVERY select_id
		$select_quotes = array();			// THERE MUST BE A boolean FOR EVERY select_id
		
		/*
			func_ids:
			[
				0 => nothing,		f(x) = x
				1 => count,			f(x) = 1		
				2 => sum,			f(x) = Sx
				3 => max,			f(x) = max(x)
				4 => min			f(x) = min(x)
				5 => avg			f(x) = avg(X)
			]
		*/

		$alias = array();
		$log_basedir_id = 0;
		$topic = "";
		$output_table = "";
		$log_dt_start = "";
		$log_dt_end = "";
		$where = array();
		$group_by = array();
		
		$query_id = md5(microtime() . mt_rand(0, mt_getrandmax()));

		// need optimization
		$sql_size_limit = 2**7;						// 128
		$read_block_size = 2**20;					// 1048576
		$group_by_rows_calc_buffer_size = 2**21;	// 2097152
		$group_dict_max_cardinality = 2**15;		// 32768		// should be set so the script would be under 10% of memory limit

		// declare sql parsing functions

		$parse_sql_select = function($start_id, $end_id) use (&$sql, &$select, &$alias, &$select_funcs) { 
			$str = strtolower(trim(substr($sql, $start_id, $end_id - $start_id)));

			$tmp = explode(",", $str);

			$count_cols = count($tmp);

			for ($i=0; $i<$count_cols; $i++){
				$col = trim($tmp[$i]);

				$tmp2 = explode(" as ", $col);

				if (count($tmp2) > 2){
					throw new Exception("too many 'as' in SELECT clause");
				} elseif (count($tmp2) == 2) {
					$alias[] = $tmp2[1];
					$is_alias = true;
				} else {
					$is_alias = false;
				}

				$col = $tmp2[0];

				$start_id = strpos($col, "(");
				if ($start_id !== false){
					// an aggregated col
					
					$end_id = strpos($col, ")");
					if ($end_id === false)
						throw new Exception("a missing parenthesis in SELECT clause");

					$func = substr($col, 0, $start_id);

					$col = substr($col, $start_id + 1, $end_id - $start_id - 1);

					if (strlen($func) > 0){
						switch ($func){
							case "count":
								$select_funcs[] = 1;
								break;
							case "sum":
								$select_funcs[] = 2;
								break;
							case "max":
								$select_funcs[] = 3;
								break;
							case "min":
								$select_funcs[] = 4;
								break;
							case "avg":
								$select_funcs[] = 5;
								break;
							default:
								throw new Exception("function not recognized in SELECT clause");
								break;
						}
					} else {
						$select_funcs[] = 0;
					}

				} else {
					$select_funcs[] = 0;
				}

				$select[] = $col;

				if (!$is_alias)
					$alias[] = $col;
			}
		};

		$parse_sql_from = function($start_id, $end_id) use (&$sql, &$log_basedir_id, &$topic, &$output_table, &$query_id, &$log_dt_start, &$log_dt_end) { 
			$str = trim(substr($sql, $start_id, $end_id - $start_id));
			
			$tmp = explode(" into ", $str);

			if (count($tmp) == 1)
				$output_table = $query_id;
			elseif (count($tmp) == 2)
				$output_table = str_replace(array("'", " "), array("", "_"), $this->sanitize($tmp[1]));
			else
				throw new Exception("problem with 'into' in FROM clause");

			$str = $tmp[0];

			$tmp = explode(" between ", $str);

			if (count($tmp) != 2)
				throw new Exception("problem with 'between' in FROM clause");

			$between = explode(" and ", $tmp[1]);
				
			if (count($between) != 2)
				throw new Exception("problem with 'between' in FROM clause");
				
			$log_dt_start = str_replace("'", "", $between[0]);
	
			if (strpos($log_dt_start, " ") === false)
				$log_dt_start .= " 00:00:00";

			$log_dt_start = strtotime($log_dt_start);

			$log_dt_end = str_replace("'", "", $between[1]);

			if (strpos($log_dt_end, " ") === false)
				$log_dt_end .= " 00:00:00";

			$log_dt_end = strtotime($log_dt_end);

			$str = $tmp[0];

			$tmp = explode(".", $str, 2);

			if (count($tmp) == 2){
				$log_basedir_id = $tmp[0];
				$topic = $tmp[1];
			} else {
				$topic = $tmp[0];
			}
		};

		$parse_sql_where = function($start_id, $end_id) use (&$sql, &$where) { 
			//echo "where: " . trim(substr($sql, $start_id, $end_id - $start_id)) . "\n"; 
		};

		$parse_sql_group_by = function($start_id, $end_id) use (&$sql, &$group_by) { 
			$str = trim(substr($sql, $start_id, $end_id - $start_id));
			$tmp = explode(",", $str);

			foreach ($tmp as $k => $v){
				$group_by_colname = trim($tmp[$k]);

				if ($group_by_colname == "")
					throw new Exception("invalid group by cols");

				$group_by[] = $tmp[$k];
			}
		};

		// parse $sql into variables

		$sql = trim($sql);

		if ($sql == "")
			throw new Exception("sql is empty");

		$sql_lc = strtolower($sql);

		$tmp = explode("select", $sql_lc);
		if (count($tmp) != 2)
			throw new Exception("sql must contain a single SELECT");

		$start_id = strlen($tmp[0]) + 6;

		$sql_lc = $tmp[1];

		$tmp = explode("from", $sql_lc);
		if (count($tmp) != 2)
			throw new Exception("sql must contain a single FROM");

		$end_id = $start_id + strlen($tmp[0]);

		// process SELECT between $start_id and $end_id
		$parse_sql_select($start_id, $end_id);

		$start_id = $end_id + 4;

		$sql_lc = $tmp[1];

		$tmp = explode("where", $sql_lc);
		$count_tmp = count($tmp);
		$sql_is_where = false;
		if ($count_tmp == 2){
			$sql_is_where = true;
			$end_id = $start_id + strlen($tmp[0]);

			// process FROM between $start_id and $end_id
			$parse_sql_from($start_id, $end_id);

			$sql_lc = $tmp[1];
			$start_id = $end_id + 5;
		} elseif ($count_tmp > 2){
			throw new Exception("sql can contain only a single WHERE");
		}

		$sql_is_group_by = false;
		$tmp = explode("group by", $sql_lc);
		$count_tmp = count($tmp);
		if ($count_tmp == 2){
			$sql_is_group_by = true;
			$end_id = $start_id + strlen($tmp[0]);

			if (!$sql_is_where){
				// process FROM between $start_id and $end_id
				$parse_sql_from($start_id, $end_id);
			} else {
				// process WHERE between $start_id and $end_id
				$parse_sql_where($start_id, $end_id);
			}

			$sql_lc = $tmp[1];
			$start_id = $end_id + 8;
		} elseif ($count_tmp > 2){
			throw new Exception("sql can contain only a single GROUP BY");
		}

		$end_id = $start_id + strlen($sql_lc);

		if (!$sql_is_where && !$sql_is_group_by){
			// process FROM between $start_id and $end_id
			$parse_sql_from($start_id, $end_id);
		} elseif ($sql_is_where && !$sql_is_group_by) {
			// process WHERE BY between $start_id and $end_id
			$parse_sql_where($start_id, $end_id);
		} elseif ($sql_is_group_by) {
			// process GROUP BY between $start_id and $end_id
			$parse_sql_group_by($start_id, $end_id);
		}

		/*
		echo $topic . "\n";
		print_r($select);
		print_r($alias);
		print_r($select_funcs);
		print_r($group_by);
		die();
		*/

		// general variables
		$headers = array();
		$header_id_to_select_ids = array();		// header_id => [select array ids]
		$mysql_data_types = array();
		$meta = "";
		$line_delimiter = "";
		$col_delimiter = "";
		$output = array();
		$output_num_inserts = 0;
		$output_num_insert_dups = 0;
		$is_group_by = false;
		$group_dict = array();
		$select_id_to_group_id = array();
		$group_id_to_select_id = array();
		$non_grouped_select_ids = array();
		$group_by_rows_calc_counter = 0;
		$count_group_by = 0;
		$group_dict_cardinality = 0;
		
		// populate $sql into relevant variables
		// $alias is populated either with the declared alias or if undeclared 
		//     then with the original col name
		// throw exceptions if some variables don't exist (like no select, topic)

		// omit cols from $group_by that doesn't exist in $select
		if (count($group_by) > 0){
			$new_group_by = array();

			foreach ($group_by as $group_id => $group_colname){
				if (in_array($group_colname, $new_group_by))
					throw new Exception("group by contains the same field more than once");

				foreach ($select as $select_id => $select_colname)
					if ($group_colname == $select_colname){
						$new_group_by[] = $group_colname;

						// first occurrence of $select_colname inside $select will be used
						// $group_id in the group level. lvl = 0 is the highest level.
						$select_id_to_group_id[$select_id] = $group_id;
						$group_id_to_select_id[$group_id] = $select_id;

						break;
					}
			}

			$group_by = $new_group_by;

			$tmp_arr = array_keys($select_id_to_group_id);
			foreach ($select as $select_id => $select_colname)
				if (!in_array($select_id, $tmp_arr))
					$non_grouped_select_ids[] = $select_id;

			$count_group_by = count($group_by);

			if ($count_group_by > 0){
				$is_group_by = true;

				$next_select_id = count($select);

				$select[$next_select_id] = "*";
				$select_funcs[$next_select_id] = 1;
				$select_quotes[$next_select_id] = false;
				$alias[$next_select_id] = "auto_group_count";
				$mysql_data_types["*"] = "int";
				$non_grouped_select_ids[] = $next_select_id;
			}

			// there shouldn't be any aggregative functions if there's no group by
		}

		// check that $log_basedir_id exists in PHPWSConfig::$logs_basedir_vols
		if (!array_key_exists($log_basedir_id, PHPWSConfig::$logs_basedir_vols))
			throw new Exception("log basedir id ('".$log_basedir_id."') not defined");

		// load meta.config and get the headers into the cols variable.
		// check that the cols in $select exist in $headers
		$meta = $this->file_logger_get_meta($topic, $log_basedir_id);
		if ($meta === false || !is_array($meta) || !array_key_exists("headers", $meta))
			throw new Exception("topic doesn't have meta file or meta file doesn't contain headers");

		$headers = $meta["headers"];
		if (!is_array($headers) || count($headers) == 0)
			throw new Exception("headers are empty or not a json array in meta file");

		$mysql_data_types_meta = $meta["mysql_data_types"];
		if (!is_array($mysql_data_types_meta) || count($mysql_data_types_meta) == 0)
			throw new Exception("mysql_data_types are empty or not a json array in meta file");

		$mysql_data_types = array();
		foreach ($headers as $k => $v){
			if (!isset($mysql_data_types_meta[$k]))
				throw new Exception("mysql_data_types not holding info for all headers");
			$mysql_data_types[$headers[$k]] = $mysql_data_types_meta[$k];
		}
		$mysql_data_types["*"] = "int";

		$sql = "create table file_logger." . $output_table . "(";
		$sql_insert_tpl = "insert into file_logger." . $output_table . " (";

		$i = 0;
		foreach ($select as $select_id => $select_colname){
			
			if ($i > 0){
				$sql .= ",";
				$sql_insert_tpl .= ",";
			}

			$sql_insert_tpl .= $alias[$select_id];
			$sql .= $alias[$select_id] . " " . $mysql_data_types[$select_colname];

			if ($select_colname == "*")
				continue;

			if (($header_id = array_search($select_colname, $headers)) !== false){
				if (!array_key_exists($header_id, $header_id_to_select_ids))
					$header_id_to_select_ids[$header_id] = array();
				$header_id_to_select_ids[$header_id][] = $select_id;

				if (!array_key_exists($select_colname, $mysql_data_types))
					throw new Exception("selected col " . $select_colname . " doesn't have a declared mysql data type");

				$data_type = $mysql_data_types[$select_colname];
				
				$short_data_type = "";
				$count_data_type = strlen($data_type);
				for ($i=0; $i<$count_data_type; $i++){
					$ch = $data_type[$i];
					if ($ch == "(")
						break;
					else
						$short_data_type .= $ch;
				}
				
				$short_data_type = strtolower($short_data_type);
				//$select_quotes
				switch ($short_data_type){
					case "char":
					case "varchar":
					case "binary":
					case "varbinary":
					case "blob":
					case "text":
					case "enum":
					case "set":
					case "date":
					case "time":
					case "datetime":
					case "timestamp":
					case "year":
						$select_quotes[$select_id] = true;
						break;
					default:
						$select_quotes[$select_id] = false;
						break;
				}

			} else {
				throw new Exception("selected col " . $select_colname . " was not found in headers list in meta file");
			}

			$i++;
		}

		$sql .= ") engine=innodb";
		$sql_insert_tpl .= ")";

		// check that the topic physically exists and there is data between
		// $log_dt_start and $log_dt_end. if both variables are empty then
		// consider all data from the topic.
		// we can start checking if the day directory in log_dt_start exists
		// and if not then advance the start day directory by one day and test again
		// this way we set the log_dt_start properly to the existent data.
		// we can do this with log_dt_end as well but we need to advance in reverse.
		if (!$this->file_logger_get_inclusive_file_range($topic, $meta, $log_dt_start, $log_dt_end, $log_basedir_id))
			throw new Exception("logs don't exist in topic with requested range");
 
		//print_r($meta);die();

		// get both col and line delimiters from meta config
		$line_delimiter = $meta["lineDelimeter"] == PHPWSConfig::$default_rolling_file_logger_line_delimiter ? PHPWSConfig::$default_rolling_file_logger_line_delimiter : $meta["lineDelimeter"];
		$col_delimiter = $meta["colDelimeter"] == PHPWSConfig::$default_rolling_file_logger_col_delimiter ? PHPWSConfig::$default_rolling_file_logger_col_delimiter : $meta["colDelimeter"];

		// connect to mysql and create the mysql output table without any indices except unique indices for the group by cols
		$db = $this->db_connect("file_logger");
		
		$db->query($sql);
		
		$output_timer = microtime(true);
		$output[date('Y-m-d H:i:s') . " (" . microtime(true) . ")"] = "query id: " . $query_id . ", output table: " . $output_table;

		if ($is_group_by){
			// we need to group by so create unique index with all grouped cols

			$group_by_str = implode(",", $group_by);

			$sql = "alter table file_logger." . $output_table . " add primary key (" . $group_by_str . ")";

			$db->query($sql);
		}

		$output[date('Y-m-d H:i:s') . " (" . microtime(true) . ")"] = "start transaction";

		$sql = array();

		// declare internal functions
		$process_group_by_calc_buffer = function() use (&$sql_size_limit, &$db, &$count_group_by, &$group_dict_cardinality, &$group_dict, &$group_id_to_select_id, &$select_quotes, &$sql_insert_tpl, &$select_funcs, &$alias, &$output_num_insert_dups, &$sql, &$group_by_rows_calc_counter) {

			$last_dict = array();
			$last_k = array();

			for ($j=0;$j<$group_dict_cardinality;$j++){
				$dict = &$group_dict;
				$row = array();
				$k=0;
				for ($group_id=0; $group_id<$count_group_by; $group_id++){

					$last_dict[$group_id] = &$dict;
					$last_k[$group_id] = $k;
					$select_id = $group_id_to_select_id[$group_id];

					if (current($dict) === false)
						break;

					$k = key($dict);
					$v = current($dict);        

					$row[$select_id] = $k;

					if ($group_id + 1 == $count_group_by){
						// last iteration

						foreach ($v as $non_select_id => $non_current_val)
							$row[$non_select_id] = $non_current_val;

						unset($dict[$k]);

						for ($l=$group_id;$l>=0;$l--){
							if (!isset($last_dict[$l]))
								continue;
					
							if (count($last_dict[$l]) == 0 && isset($last_dict[$l-1]))
								unset($last_dict[$l-1][$last_k[$l]]);
						}

					} else {
						// not the last iteration
						$dict = &$dict[$k];    
					}
				}

				// now $row contains all row data

				//print_r($row);

		
				$sql_local = "";

				$add_comma = "";
				foreach ($row as $select_id => $current_val){
					if ($select_quotes[$select_id])
						$sql_local .= $add_comma . "'" . $current_val . "'";
					else
						$sql_local .= $add_comma . $current_val;
					$add_comma = ",";
				}

				$sql_local = "(" . $sql_local . ")";

				// update only cols with aggregative functions - NO QUOTES ALL NUMS (AND NO DATETIME!)
				$sql_local = $sql_insert_tpl . " values " . $sql_local . " on duplicate key update ";

				$add_comma = "";
				foreach ($select_funcs as $select_id => $func_id){
					$select_col_name = $alias[$select_id];
					switch ($func_id){
						case 1:
							// count(*)
						case 2:
							// sum
							$sql_local .= $add_comma . $select_col_name . "=" . $select_col_name . "+" . $row[$select_id];
							break;
						case 3:
							// max
							$sql_local .= $add_comma . $select_col_name . "=if(".$row[$select_id].">".$select_col_name.",".$row[$select_id].",".$select_col_name.")";
							break;
						case 4:
							// min
							$sql_local .= $add_comma . $select_col_name . "=if(".$row[$select_id]."<".$select_col_name.",".$row[$select_id].",".$select_col_name.")";
							break;
						default:
					}

					if ($func_id != 0)
						$add_comma = ",";
				}
		
				//echo $sql_local . "\n";

				$output_num_insert_dups++;

				$sql[] = $sql_local;

				if (count($sql) > $sql_size_limit){

					//print_r($sql);
					$db->query($sql);
					$sql = array();
				}
			}

			$group_by_rows_calc_counter = 0;
			$group_dict = array();
			$group_dict_cardinality = 0;
		};

		// loop until no more log files
		$num_files = 0;
		$log_filename = $this->file_logger_get_next_filename($topic, $meta, $output);

		while ($log_filename !== false){
				
			$fh = fopen($log_filename, 'r');

			if ($fh){

				$header_id = -1;		// first header id is part of the log_dt
				$row = array();			// should contain the sequence of cols according to sql select
				$val = "";
				$is_continue_to_next_row = false;

				// loop until end of file
				while (!feof($fh)){

					$data = fread($fh, $read_block_size);

					// loop until end of read block
					$count_data = strlen($data);
					for ($ch_i=0; $ch_i<$count_data; $ch_i++){
						$ch = $data[$ch_i];

						if ($ch == $line_delimiter){
							
							// with using dictionaries
							if (!$is_continue_to_next_row && count($row) > 0){

								// insert row to mysql

								if (!$is_group_by){

									// every row in log files is added to mysql directly
									// TODO: use values (...), (...), ...

									$sql_local = "";

									$add_comma = "";

									foreach ($select as $select_id => $v){
										if ($select_quotes[$select_id])
											$sql_local .= $add_comma . "'" . $row[$select_id] . "'";
										else
											$sql_local .= $add_comma . $row[$select_id];
										$add_comma = ",";										
									}

									$output_num_inserts++;

									$sql[] = $sql_insert_tpl . " values (" . $sql_local . ")";

									if (count($sql) > $sql_size_limit){
										$db->query($sql);
										$sql = array();
									}

								} else {
									// group by

									$row[] = "1";	// for auto group count

									// use $group_dict to hold aggregated data before sending to mysql
									// $row -> $group_dict

									$dict = &$group_dict;
									foreach ($group_by as $group_id => $group_colname){
										$select_id = $group_id_to_select_id[$group_id];
										$current_val = $row[$select_id];

										if (!array_key_exists($current_val, $dict)){
											$dict[$current_val] = array();
											$group_dict_cardinality++;
										}

										$dict = &$dict[$current_val];
									}

									foreach ($non_grouped_select_ids as $k => $select_id){
										$current_val = $row[$select_id];
										$func_id = $select_funcs[$select_id];

										if ($func_id == 1)
											$current_val = 1;

										if (!array_key_exists($select_id, $dict)){
											$dict[$select_id] = $current_val;
										} else {
											switch ($func_id){
												case 1:
													// count
												case 2:
													// sum
													$dict[$select_id] += $current_val;
													break;
												case 3:
													// max
													if ($current_val > $dict[$select_id])
														$dict[$select_id] = $current_val;
													break;
												case 4:
													// min
													if ($current_val > $dict[$select_id])
														$dict[$select_id] = $current_val;
													break;
											}
										}
									}

									$group_by_rows_calc_counter++;

									if ($group_by_rows_calc_counter > $group_by_rows_calc_buffer_size || $group_dict_cardinality > $group_dict_max_cardinality){
										// $group_dict -> $row
										$process_group_by_calc_buffer();
									}
								}
							}

							$header_id = -1;
							$row = array();
							$is_continue_to_next_row = false;
						} elseif ($ch == $col_delimiter){

							// check $where. if $where returns false then set $is_continue_to_next_row to TRUE

							if ($header_id == -1){
								// $header_id = -1 is part of the log_dt
							} elseif (!$is_continue_to_next_row && array_key_exists($header_id, $header_id_to_select_ids)){
								// if this $header_id is used in $select then use it
								$select_ids = $header_id_to_select_ids[$header_id];
								foreach ($select_ids as $k => $select_id)
									$row[$select_id] = $val;
							}

							$header_id++;
							$val = "";
						} elseif (!$is_continue_to_next_row) {
							$val .= $ch;
						}

					}

				}
			} else {
				$output[date('Y-m-d H:i:s') . " (" . microtime(true) . ")"] = "the file " . $log_filename . " cannot be opened";
			}

			$num_files++;

			$log_filename = $this->file_logger_get_next_filename($topic, $meta, $output);
		}

		if ($group_by_rows_calc_counter > 0){
			// $group_dict -> $row
			$process_group_by_calc_buffer();
		}

		if (count($sql) > 0){
			$db->query($sql);
			$sql = array();
		}
		
		$output_timer = microtime(true) - $output_timer;

		$output2 = array();
		$output2[] = "end transaction";
		$output2[] = "total ms: " . $output_timer;
		$output2[] = "total sql inserts: " . $output_num_inserts;
		$output2[] = "total sql insert with dups: " . $output_num_insert_dups;
		$output2[] = "real start log dt: " . $meta["real_log_dt_start"];
		$output2[] = "real end log dt: " . $meta["real_log_dt_end"];
		$output2[] = "num of log files: " . $num_files;

		$output[date('Y-m-d H:i:s') . " (" . microtime(true) . ")"] = $output2;

		return $output;


		/*

		select col1, col2, col3, sum(col4) as sum_col4, log_dt(), max(col5), min(col6)   -- , avg(col2), rank(col1, col2)
		from 0.topic1 between '2017-01-01 00:00:00' and '2017-01-02' {into topic1_output_db}
		where (col1 > ? and col2 < '2017-09-01 00:00:00') or col5 = 'a'
		group by col1, col2, col3

		reading order is always ordered by the log datetime itself
		so the rank function ranks according to when log is received
		count(*) can only be on * and nothing else and automatically added (as group_count) when there's group by cols - can't put in sql as it is added automatically!
		sum(colX) can be used only when there's group by cols
		log_dt() produces the log datetime
		if casting doesn't take place then the col is considered as string 

		additional functions: year(), month(), day(), hour(), minute(), second()

		check the topic exists on $log_basedir_id
		load the meta config file
		get the col names from the headers in meta file
		check that the $select cols exist in 

		*/

	}

	protected function file_logger_get_meta(&$topic, &$log_basedir_id){
		if (!array_key_exists($log_basedir_id, PHPWSConfig::$logs_basedir_vols))
			return false;

		$meta_path = PHPWSConfig::$logs_basedir_vols[$log_basedir_id] . DS . $topic . DS . "meta.config";
		
		$meta_str = @file_get_contents($meta_path);

		if ($meta_str === false)
			return false;

		$meta = json_decode($meta_str, true);

		return $meta;
	}

	protected function file_logger_get_inclusive_file_range(&$topic, &$meta, &$log_dt_start, &$log_dt_end, &$log_basedir_id){

		$basedir = PHPWSConfig::$logs_basedir_vols[$log_basedir_id] . DS . $topic;

		if (!file_exists($basedir))
			throw new Exception("basedir (" . $basedir . ") doesn't exist");

		$dt1 = $log_dt_start;
		$dt2 = $log_dt_end;

		$dir1 = $basedir . DS . date($meta["dirNameDateFormat"], $dt1);

		while (!file_exists($dir1)){
			// advance one day
			$dt1 = strtotime($dt1 . " + 1 day");

			if ($dt1 > $dt2)
				return false;

			$dir1 = $basedir . DS . date($meta["dirNameDateFormat"], $dt1);
		}

		$dir2 = $basedir . DS . date($meta["dirNameDateFormat"], $dt2);

		while (!file_exists($dir2)){
			// go back one day
			$dt2 = strtotime($dt2 . " - 1 day");

			if ($dt1 > $dt2)
				return false;

			$dir2 = $basedir . DS . date($meta["dirNameDateFormat"], $dt2);
		}

		if ($dt1 > $dt2)
			return false;

		$meta["basedir"] = $basedir;
		$meta["real_log_dt_start"] = $dt1;
		$meta["real_log_dt_end"] = $dt2;
		$meta["current_log_dt"] = $dt1;

		return true;
	}

	protected function file_logger_get_next_filename(&$topic, &$meta, &$output){

		$basedir = $meta["basedir"];

		$dt_current = $meta["current_log_dt"];
		
		if ($dt_current >= $meta["real_log_dt_end"])
			return false;

		$filename = $basedir . DS . date($meta["dirNameDateFormat"], $dt_current) . DS . date($meta["fileNameDateFormat"], $dt_current) . "." . $meta["fileExtension"];

		while (!file_exists($filename)){
			$dt_current = strtotime($dt_current . " + 1 hour");

			if ($dt_current > $meta["real_log_dt_end"])
				return false;

			$filename = $basedir . DS . date($meta["dirNameDateFormat"], $dt_current) . DS . date($meta["fileNameDateFormat"], $dt_current) . "." . $meta["fileExtension"];
		}

		$meta["current_log_dt"] = strtotime(date('Y-m-d H:i:s', $dt_current)."+1 hour", $dt_current);

		return $filename;
	}

	/* End Logging Methods */



	/* Cron Methods */

	protected function start_crons(){
		if (!$this->is_admin())
			throw new Exception("must be an admin");

		exec("crontab /var/www/html/includes/crontab.txt", $output);

		return $output;
	}

	protected function get_crons(){
		if (!$this->is_admin())
			throw new Exception("must be an admin");

		$crons_file = @file_get_contents(INCLUDES_DIR . DS . "crontab.txt");
		$crons_arr = array();

		if ($crons_file !== false){
			$crons_file = str_replace("\n\n", "", $crons_file);
			
			if (strlen($crons_file) > 0)
				$crons_arr = explode("\n", $crons_file);
		}

		exec("crontab -l", $output);

		return array("file" => $crons_arr, "crontab" => $output);
	}

	protected function add_cron_to_file($b64_cron = ""){
		if (!$this->is_admin())
			throw new Exception("must be an admin");

		if ($b64_cron == "")
			return true;

		$cron = base64_decode($b64_cron) . "\n";

		$crons = @file_get_contents(INCLUDES_DIR . DS . "crontab.txt");

		if ($crons === false){
			// path doesn't exist so create it

			@mkdir(INCLUDES_DIR);

			$crons = "\n\n";

			if (@file_put_contents(INCLUDES_DIR . DS . "crontab.txt", $crons) == false)
				throw new Exception("not enough privileges to create includes dir");
		}

		if (strpos($crons, $cron) !== false)
			return true;

		$crons = $cron . $crons;

		@file_put_contents(INCLUDES_DIR . DS . "crontab.txt", $crons);

		return true;
	}

	protected function remove_cron_from_file($b64_cron = ""){
		if (!$this->is_admin())
			throw new Exception("must be an admin");

		if ($b64_cron == "")
			return true;

		$cron = base64_decode($b64_cron) . "\n";

		$crons = @file_get_contents(INCLUDES_DIR . DS . "crontab.txt");

		if ($crons === false)
			return true;

		$crons = str_replace($cron, "", $crons);

		if ($crons == "\n\n")
			return $this->remove_all_crons();

		@file_put_contents(INCLUDES_DIR . DS . "crontab.txt", $crons);

		return true;
	}

	protected function stop_crons(){
		if (!$this->is_admin())
			throw new Exception("must be an admin");

		exec("crontab -r");

		return true;
	}

	/* End Cron Methods */
}