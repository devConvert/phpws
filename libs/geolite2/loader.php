<?php

use GeoIp2\Database\Reader;

include_once LIBS_DIR . DS . "geolite2" . DS . "ProviderInterface.php";

include_once LIBS_DIR . DS . "geolite2" . DS . "Database" . DS . "Reader.php";

include_once LIBS_DIR . DS . "geolite2" . DS . "Exception" . DS . "GeoIp2Exception.php";
include_once LIBS_DIR . DS . "geolite2" . DS . "Exception" . DS . "AddressNotFoundException.php";
include_once LIBS_DIR . DS . "geolite2" . DS . "Exception" . DS . "HttpException.php";
include_once LIBS_DIR . DS . "geolite2" . DS . "Exception" . DS . "InvalidRequestException.php";
include_once LIBS_DIR . DS . "geolite2" . DS . "Exception" . DS . "OutOfQueriesException.php";

include_once LIBS_DIR . DS . "geolite2" . DS . "Model" . DS . "AbstractModel.php";
include_once LIBS_DIR . DS . "geolite2" . DS . "Model" . DS . "Country.php";

include_once LIBS_DIR . DS . "geolite2" . DS . "Record" . DS . "AbstractPlaceRecord.php";
include_once LIBS_DIR . DS . "geolite2" . DS . "Record" . DS . "AbstractRecord.php";
include_once LIBS_DIR . DS . "geolite2" . DS . "Record" . DS . "City.php";
include_once LIBS_DIR . DS . "geolite2" . DS . "Record" . DS . "Continent.php";
include_once LIBS_DIR . DS . "geolite2" . DS . "Record" . DS . "Country.php";
include_once LIBS_DIR . DS . "geolite2" . DS . "Record" . DS . "Location.php";		
include_once LIBS_DIR . DS . "geolite2" . DS . "Record" . DS . "MaxMind.php";		
include_once LIBS_DIR . DS . "geolite2" . DS . "Record" . DS . "Postal.php";		
include_once LIBS_DIR . DS . "geolite2" . DS . "Record" . DS . "RepresentedCountry.php";		
include_once LIBS_DIR . DS . "geolite2" . DS . "Record" . DS . "Subdivision.php";		
include_once LIBS_DIR . DS . "geolite2" . DS . "Record" . DS . "Traits.php";		

function GetGeoLite2Reader(){

    $reader = new GeoIp2\Database\Reader(LIBS_DIR . DS . "geolite2" . DS . "GeoLite2-Country.mmdb");

    return $reader;

}