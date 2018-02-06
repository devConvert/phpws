<?php

include "geoip.php";

$ip = !empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];
$ip_arr = explode(",", $ip);
$ip = $ip_arr[0];

$geoip = geoip_open("geoip.dat", GEOIP_MEMORY_CACHE);
$country_code = geoip_country_code_by_addr($geoip, $ip);

echo "Your IP is " . $ip . "<br>And you country code is " . $country_code;