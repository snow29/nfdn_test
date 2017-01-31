<?php

################################################################
# This software is the unpublished, confidential, proprietary, 
# intellectual property of zipperSNAP, LLC and may not be copied,
# duplicated, retransmitted or used in any manner without
# expressed written consent from zipperSNAP, LLC.
# Copyright 2009 - Present, zipperSNAP, LLC.
################################################################
 
$reqHeadersAry = getallheaders(); 

// $_SERVER['REMOTE_ADDR'] = $reqHeadersAry["X-Forwarded-For"];

function get_IP_address()
{
    foreach (array('HTTP_CLIENT_IP',
                   'HTTP_X_FORWARDED_FOR',
                   'HTTP_X_FORWARDED',
                   'HTTP_X_CLUSTER_CLIENT_IP',
                   'HTTP_FORWARDED_FOR',
                   'HTTP_FORWARDED',
                   'REMOTE_ADDR') as $key){
        if (array_key_exists($key, $_SERVER) === true){
            foreach (explode(',', $_SERVER[$key]) as $IPaddress){
                $IPaddress = trim($IPaddress); // Just to be safe

                if (filter_var($IPaddress,
                               FILTER_VALIDATE_IP,
                               FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
                    !== false) {

                    return $IPaddress;
                }
            }
        }
    }
}


#To Get the Location Details(City,State,region,Address,Country,Country Code,Continent,Continent Code) Code Start
function ip_info($ip = NULL, $purpose = "location", $deep_detect = TRUE) {
    $output = NULL;
    if (filter_var($ip, FILTER_VALIDATE_IP) === FALSE) {
        $ip = $_SERVER["REMOTE_ADDR"];
        if ($deep_detect) {
            if (filter_var(@$_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP))
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            if (filter_var(@$_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP))
                $ip = $_SERVER['HTTP_CLIENT_IP'];

        }

    }
    $purpose    = str_replace(array("name", "\n", "\t", " ", "-", "_"), NULL, strtolower(trim($purpose)));
    $support    = array("country", "countrycode", "state", "region", "city", "location", "address");
    $continents = array(
        "AF" => "Africa",
        "AN" => "Antarctica",
        "AS" => "Asia",
        "EU" => "Europe",
        "OC" => "Australia (Oceania)",
        "NA" => "North America",
        "SA" => "South America"
    );
    if (filter_var($ip, FILTER_VALIDATE_IP) && in_array($purpose, $support)) {

        $ipdat = @json_decode(file_get_contents("http://www.geoplugin.net/json.gp?ip=" . $ip));
        if (@strlen(trim($ipdat->geoplugin_countryCode)) == 2) {
            switch ($purpose) {
                case "location":
                    $output = array(
                        "city"           => @$ipdat->geoplugin_city,
                        "state"          => @$ipdat->geoplugin_regionName,
                        "country"        => @$ipdat->geoplugin_countryName,
                        "country_code"   => @$ipdat->geoplugin_countryCode,
                        "continent"      => @$continents[strtoupper($ipdat->geoplugin_continentCode)],
                        "continent_code" => @$ipdat->geoplugin_continentCode
                    );
                    break;


                case "address":
                    $address = array($ipdat->geoplugin_countryName);
                    if (@strlen($ipdat->geoplugin_regionName) >= 1)
                        $address[] = $ipdat->geoplugin_regionName;
                    if (@strlen($ipdat->geoplugin_city) >= 1)
                        $address[] = $ipdat->geoplugin_city;
                    $output = implode(", ", array_reverse($address));
                    break;
                case "city":
                    $output = @$ipdat->geoplugin_city;
                    break;
                case "state":
                    $output = @$ipdat->geoplugin_regionName;
                    break;
                case "region":
                    $output = @$ipdat->geoplugin_regionName;
                    break;
                case "country":
                    $output = @$ipdat->geoplugin_countryName;
                    break;
                case "countrycode":
                    $output = @$ipdat->geoplugin_countryCode;
                    break;
            }
        }
    }
    return $output;
}

#Country Code Ends

# Get Country Code
$_SERVER['REMOTE_ADDR'] = get_IP_address($IPaddress);

//identify requests from bot
$botAgent = 0;
if(isset($_SERVER['HTTP_USER_AGENT'])){$usr_agent = $_SERVER['HTTP_USER_AGENT']; }
//$usr_agent = 'Googlebot';          
$botAgent = preg_match('/Slurp|Baiduspider|bingbot|MJ12bot|Googlebot|spbot|DotBot|YandexBot|AhrefsBot|rogerbot|ysearch|Exabot|Sogou|CCBot|msnbot|SurveyBot|EasouSpider|meanpathbot|360Spider|AcoonBot|SeznamBot|Aboundex|A6-Indexer|SearchmetricsBot|^$/i',$usr_agent);

mb_language('uni');
mb_internal_encoding('UTF-8');
//date_default_timezone_set('America/Denver');
date_default_timezone_set('MST');
include_once "class.phpmailer.php";

// initialising master id in array
$master_id_array=array('1','1');

// for emergencies!
//$gDontLogError = false;
//$gDontUseGetPrice = false;
//

$gTimeOut = 30;
$globalTimeOut = 20;#EndUser Authentication session timeout limit
$gDontCheckTimeout = false;
$gLoggedIn = false;
$gLoginTimedOut = false;
$globalLoggedTimedOut = false;
$gAdministratorFlag = false;
$gDebug = false;
$gUserId = 0;
$gDealerId = 0;
$gDefaultDealerId = 1;
$gDistributorSet = ""; // depricate this?
$gDealerDistributors = array();
$gUserName = "";
$gUserTypeId = 0;
$gSystemPreferences = array();
$gUserRow = array();
$systemCode = "NFDN";
$globalSystemCode = "SECURENFDN";
$gDevelopmentServer = false;
$globalLoggedIn = false;
$globalUserName="";
//$cloudFrontURL = "http://nfdn.images.s3.amazonaws.com/"; 
$cloudFrontURL = "https://gearfireimages.s3.amazonaws.com/";

if (is_numeric(substr($_SERVER['HTTP_HOST'],0,2)) || $_SERVER['HTTP_HOST'] == "localhost" || $_SERVER['HTTP_HOST'] == "nfdn.site") {
	$gDevelopmentServer = true;
	$gDontRecordHit = true;
}
$gExtraCode = "";
$gLivePriceIndex = 0;

// store internal IPs for all web servers in the cluster so memcache can use distributed cache
$gMemcacheServers = array(
    "127.0.0.1" //xlprod1
);

$browserRejects = array("Baiduspider","Yandex","twenga","majestic12","thefind","ahrefs");
$ipAddressRejects = array("100.43.83.129","38.99.82.");
foreach ($browserRejects as $browser) {
	if (strpos($_SERVER['HTTP_USER_AGENT'],$browser) !== false) {
		header("Location: /usonly.html");
		exit;
	}
}
foreach ($ipAddressRejects as $ipAddress) {
	if (substr($_SERVER['REMOTE_ADDR'],0,strlen($ipAddress)) == $ipAddress) {
		header("Location: /usonly.html");
		exit;
	}
}

include_once "connect.inc";

set_error_handler("customErrorHandler",E_ERROR | E_RECOVERABLE_ERROR | E_WARNING);
function customErrorHandler($errno,$errstr) {
	logError("Error Number: " . $errno . "\n  Error Text:" . $errstr);
}

$gMonthArray = array("1"=>"January","2"=>"February","3"=>"March","4"=>"April","5"=>"May","6"=>"June","7"=>"July","8"=>"August","9"=>"September","10"=>"October","11"=>"November","12"=>"December");
$stateArray = array(
	"AK", "AL", "AR", "AZ", "CA", "CO", "CT", "DC", "DE", "FL",
	"GA", "HI", "IA", "ID", "IL", "IN", "KS", "KY", "LA", "MA",
	"MD", "ME", "MI", "MN", "MO", "MS", "MT", "NC", "ND", "NE",  
	"NH", "NJ", "NM", "NV", "NY", "OH", "OK", "OR", "PA", "PR", "RI", 
	"SC", "SD", "TN", "TX", "UT", "VA", "VT", "WA", "WI", "WV", "WY"
);
$stateNames = array(
	"AK"=>"Alaska", "AL"=>"Alabama", "AR"=>"Arkansas", "AZ"=>"Arizona", "CA"=>"California", "CO"=>"Colorado", "CT"=>"Connecticut", "DC"=>"District of Columbia", "DE"=>"Delaware", "FL"=>"Florida",
	"GA"=>"Georgia", "HI"=>"Hawaii", "IA"=>"Iowa", "ID"=>"Idaho", "IL"=>"Illinois", "IN"=>"Indiana", "KS"=>"Kansas", "KY"=>"Kentucky", "LA"=>"Louisiana", "MA"=>"Massachusetts",
	"MD"=>"Maryland", "ME"=>"Maine", "MI"=>"Michigan", "MN"=>"Minnesota", "MO"=>"Missouri", "MS"=>"Mississippi", "MT"=>"Montana", "NC"=>"North Carolina", "ND"=>"North Dakota", "NE"=>"Nebraska",  
	"NH"=>"New Hampshire", "NJ"=>"New Jersey", "NM"=>"New Mexico", "NV"=>"Nevada", "NY"=>"New York", "OH"=>"Ohio", "OK"=>"Oklahoma", "OR"=>"Oregon", "PA"=>"Pennsylvania", "PR"=>"Puerto Rico", "RI"=>"Rhode Island", 
	"SC"=>"South Carolina", "SD"=>"South Dakota", "TN"=>"Tennessee", "TX"=>"Texas", "UT"=>"Utah", "VA"=>"Virginia", "VT"=>"Vermont", "WA"=>"Washington", "WI"=>"Wisconsin", "WV"=>"West Virginia", "WY"=>"Wyoming"
);
$dealerExceptionManufacturers=array(155); 
if ((!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || $_GET['ajax'] == "true") {
	$gDontRecordHit = true;
} else {

	if (array_key_exists("REMOTE_ADDR",$_SERVER)) {
		$ipAddress = $_SERVER['REMOTE_ADDR'];
		$resultSet = executeQuery("update ip_address_accesses set access_count = access_count + 1 where ip_address = ?",$ipAddress);
		if ($resultSet['affected_rows'] == 0) {
			$gDontLogError = true;
			$resultSet = executeQuery("insert into ip_address_accesses (ip_address_access_id,ip_address,access_count) values " .
				"(null,?,1)",$ipAddress);
			if (!empty($resultSet['sql_error'])) {
				executeQuery("update ip_address_accesses set access_count = access_count + 1 where ip_address = ?",$ipAddress);
			}
			$gDontLogError = false;
		}
	}
	
	# Look for white-listed or black-listed IP address
	// echo "ip:".$ipAddress;
	// echo "DS:".$gDevelopmentServer;

	if (!$gDevelopmentServer && !empty($ipAddress)) {
		//echo $ipAddress;
		# Look for white-listed IP address

		$resultSet = executeQuery("select * from ip_address_whitelist where ip_address = ?",$ipAddress);
		if ($resultSet['row_count'] == 0) {
			# Look for countries outside north america and redirect to US only page
			// $ipAddressParts = explode(".",$ipAddress);
			// $ipAddressNumber = $ipAddressParts[0] * 16777216 + $ipAddressParts[1] * 65536 + $ipAddressParts[2] * 256 + $ipAddressParts[3];
			// //$resultSet = executeQuery("select * from ip_address_countries where $ipAddressNumber between start_address and end_address");
			// $resultSet = executeQuery("select * from ip_address_countries where start_address <= $ipAddressNumber and start_address >= $ipAddressNumber-16777215  and end_address >= $ipAddressNumber");
			// if ($row = getNextRow($resultSet)) {
			// 	if ($row['country_code'] != "US" && $row['country_code'] != "CA"  && $row['country_code'] != "PR")
			// 	 {
			// 		header("Location: /usonly.html");
			// 		exit;
			// 	}
			// }

            $Country_Code=ip_info("Visitor", "Country Code");

if ($Country_Code != "US" && $Country_Code != "CA"  && $Country_Code != "PR" && $Country_Code != "IN")
				 {
					header("Location: /usonly.html");
					exit;
				}

		}
	}
		
	# Look for hacking attempts and check ip address. If the url indicates a clear hacking attempt, immediately log the IP address and
	# go to the rejectedip.html page.
	$foundAttempt = false;
	$rejected = false;
	
	if (!(stripos($_SERVER['REQUEST_URI'],"passwd") === false)) {
		$foundAttempt = true;
		$rejected = true;
	}
	
	if($botAgent)
	{
		$server_parameter = $_SERVER['REQUEST_URI'];
		
	}
	else
	{
		$server_parameter = $usr_agent;
	}
	$rejected_criteria = ($rejected ? 'Attempt Found' :  'Bot Found');


	if ($foundAttempt || $botAgent) {
		if (!empty($ipAddress) && $ipAddress != "127.0.0.1" && $ipAddress != "172.16.98.172") {
			$resultSet = executeQuery("select * from rejected_ip_addresses where ip_address = ?",$ipAddress);
			if (!$row = getNextRow($resultSet)) {
				executeQuery("insert into rejected_ip_addresses (rejected_ip_address_id,ip_address,rejected_date_time,rejected_criteria,server_parameter,version) values " .
					"(null,?,now(),?,?,1)",$ipAddress,$rejected_criteria,$server_parameter);
			}
		}
		header("Location: rejectedip.html");
		exit;
	} else {
		$resultSet = executeQuery("select * from rejected_ip_addresses where ip_address = ?",$_SERVER['REMOTE_ADDR']);
		if ($row = getNextRow($resultSet)) {
			header("Location: rejectedip.html?" . str_replace(".","",$_SERVER['REMOTE_ADDR']));
			exit;
		}
	}
	
}

$gProgramId = getFieldFromId("program_id","programs","program_code",$gProgramCode);
if (!empty($gProgramId)) {
	$gProgramTitle = getFieldFromId("title_text","programs","program_id",$gProgramId);
	$gProgramHelp = htmlspecialchars(getFieldFromId("help_text","programs","program_id",$gProgramId),ENT_QUOTES,"UTF-8");
	$gAllUserAccess = getFieldFromId("all_user_access","programs","program_id",$gProgramId);
}

if (!$gDontCheckTimeout && is_array($_SESSION['timeout_data']) && !$loggingIn) {
	if ($_SERVER['REQUEST_URI'] == $_SESSION['timeout_data']['REQUEST_URI'] && count($_POST) == 0) {
		$_POST = $_SESSION['timeout_data']['post'];
		$_GET = $_SESSION['timeout_data']['get'];
	}
	unset($_SESSION['timeout_data']);
}

# If not logging in, if the user is already logged in, get their information from the database
if (!$loggingIn) {
	if (array_key_exists($systemCode,$_SESSION) && array_key_exists("user_id",$_SESSION[$systemCode]) && array_key_exists("last_hit",$_SESSION[$systemCode]) && array_key_exists("login_ip_address",$_SESSION[$systemCode]) &&
		$_SERVER['REMOTE_ADDR'] == $_SESSION[$systemCode]['login_ip_address']) {
		//$resultSet = executeQuery("select * from users where user_id = ? and inactive = 0",$_SESSION[$systemCode]['user_id']);
		$resultSet = executeQuery("select * from users left join contacts using (contact_id) where user_id = ?".($_SESSION[$systemCode]['user_id']!=2?" and inactive = 0":""),$_SESSION[$systemCode]['user_id']);
            if ($row = getNextRow($resultSet)) {
			$gLoggedIn = true;
			$gUserId = $_SESSION[$systemCode]['user_id'];
			$gUserName = $row['user_name'];
			$gUserTypeId = $row['user_type_id'];
			$gAdministratorFlag = $row['administrator_flag'] == 1;
			$gUserRow = $row;
			if ($gUserTypeId > 0) { // don't record hits for admins and dealers
				$gDontRecordHit = true;
			}
		}
	}
}

# Get the dealer_id for this domain or user. Logout if the user has different dealer than domain
$gDomain = strtolower(str_replace("www.","",$_SERVER['HTTP_HOST']));

// is there a better place/way to do this?
switch($gDomain) {
	case "coltnfdmall.com":
		header("Location: http://nfdmall.com/colt/");
		exit;
	case "smith-wessonnfdmall.com":
		if (!empty($_GET['upc']) && is_numeric($_GET['upc'])) {
			header("Location: http://buynfdn.com/smith-wesson/catalog_detail.php?upc=" . $_GET['upc']);
		} else {
			header("Location: http://buynfdn.com/smith-wesson/");
		}
		exit;
	case "usm4.com":
		header("Location: http://buynfdn.com/usm4/");
		exit;
	case "libertyammonfdmall.com":
		//header("Location: http://nfdmall.com/liberty/");
		header("Location: http://buynfdn.com/usm4/");
		exit;
	case "tcarmsnfdmall.com":
		header("Location: http://buynfdn.com/thompsoncenter/");
		exit;
	case "nfdnshipping.com":
		if (!$gLoggedIn && strpos($_SERVER['REQUEST_URI'],"/login.php") === false) {
			header("Location: http://buynfdn.com/login.php");
			exit;
		}
}

$domainredirect = false;
$reqDomain = $gDomain;
if($gDomain == 'nfdnpartners.com')
{
   $domainredirect = true;
   $gDomain = $_GET["dealer"];

   if($gDomain == '')
   {
      $gDomain = $_COOKIE["dealer"];
   }

   if($gDomain == '')
   {
      $domainredirect = false;
      $gDomain = $reqDomain;
   }
   else
   {
      setcookie("dealer", $gDomain, 0, "/");
   }
}
else
{
   setcookie("dealer", "", 0, "/");
}

// dealers can be set to inacitve, so need to check
$resultSet = executeQuery("select dealer_id from domain_names left join dealers using (dealer_id) where domain_name = ? and dealers.inactive = 0",$gDomain);

$row = getNextRow($resultSet);
$gDealerId = $row['dealer_id'];
$userDealerId = getFieldFromId("dealer_id","users","user_id",$gUserId);
if (empty($gDealerId) || $gDealerId == $gDefaultDealerId) {
	$gDealerId = $userDealerId;
}
if (!empty($userDealerId) && !empty($gDealerId) && $userDealerId != $gDealerId) {
	logout();
}

if (empty($gDealerId)) {
	$gDealerId = $gDefaultDealerId;
}

$gTimeOut = getPreference('TIMEOUT');
if (empty($gTimeOut)) {
	$gTimeOut = 120;
}
if ($gLoggedIn && array_key_exists("last_hit",$_SESSION[$systemCode]) && time() > ($_SESSION[$systemCode]['last_hit'] + ($gTimeOut * 60))) {
	$gLoginTimedOut = true;
	executeQuery("insert into security_log (log_id,security_log_type,user_name,ip_address,log_entry,entry_time,version) values " .
		"(null,'USER-TIMEOUT',?,?,'User timed out',now(),1)",array($gUserName,$_SERVER['REMOTE_ADDR']));
	logout();
}
#Enduser Authentication timeout check
#$globalTimeOut = empty(getPreference('GLOBALTIMEOUT'))?30:getPreference('GLOBALTIMEOUT');
if(isset($_SESSION[$globalSystemCode]['global_user_id'])){ 
    if(isset($_SESSION[$globalSystemCode]['globalUserLastHit']) && (time() - $_SESSION[$globalSystemCode]['globalUserLastHit'] ) > ($globalTimeOut * 60) ){
        $globalLoggedTimedOut = true;
        globalLogout();
        $queryString = urlencode($_SERVER['REQUEST_URI']);
        header("Location: index.php?page=" . ($globalLoggedTimedOut ? "re" : "") . "login&url=$queryString");
        exit;
    }  
    #Enduser Authtication hit update
    $_SESSION[$globalSystemCode]['globalUserLastHit'] =time();
}
#Handling buynfdn.com domain session timeout
    if(isset($_SESSION['global_user_session_data']) && (time() - $_SESSION['last_activity'] ) > ($globalTimeOut * 60) ){
        $globalLoggedTimedOut = true;         
       /*
        $res = executeQuery("Select dealer_domain from domain_names join shopping_carts using(dealer_id) where shopping_cart_id =?",$_SESSION['shopping_cart_id']);
        $row = getNextRow($res);
        $queryString = urlencode($_SERVER['REQUEST_URI']);        
        header("Location: http://nfdndevcom.devmsts.com/index.php?page=" . ($globalLoggedTimedOut ? "re" : "") . "login&url=$queryString");
        */
    }  
    if(isset($_SESSION['global_user_session_data'])){
    #Enduser Authtication hit update
    $_SESSION['last_activity'] =time();
}
if ($gLoggedIn) {
	$_SESSION[$systemCode]['last_hit'] = time();
}

if (!$gLoggedIn) {
	if (!canRunProgram($gProgramId)) {
		$queryString = urlencode($_SERVER['REQUEST_URI']);
		header("Location: login.php?page=" . ($gLoginTimedOut ? "re" : "") . "login&url=$queryString");
		exit;
	}
} else {
	if (!canRunProgram($gProgramId)) {
		header("Location: index.php");
		exit;
	}
}
if (empty($gUserId)) {
	$gUserId = "";
}

if (!$gDontRecordHit) {
	$logHit = true;
	$resultSet = executeQuery("select program_id,public_access from programs where program_code = ?",$gProgramCode);
	if ($row = getNextRow($resultSet)) {
		$programId = $row['program_id'];
		$logHit = $row['public_access'] == 1;
	} else {
		$logHit = false;
	}
	if (strpos($_SERVER['HTTP_USER_AGENT'],"bot") !== false) {
		$logHit = false;
	}
	if (strpos($_SERVER['HTTP_USER_AGENT'],"crawler") !== false) {
		$logHit = false;
	}
	if (strpos($_SERVER['HTTP_USER_AGENT'],"spider") !== false) {
		$logHit = false;
	}
	if (strpos($_SERVER['HTTP_USER_AGENT'],"Feedfetcher") !== false) {
		$logHit = false;
	}
	if ($_SERVER['HTTP_X_REQUESTED_WITH'] == "XMLHttpRequest") {
		$logHit = false;
	}

	$dealerId = (empty($gDealerId) ? $gDefaultDealerId : $gDealerId);
	$domainName = $_SERVER['SERVER_NAME'];
	if (substr($domainName,0,4) == "www.") {
		$domainName = substr($domainName,4);
	}

	if ($logHit && !empty($_SERVER['SERVER_NAME'])) {
		if (!empty($domainName)) {
			$resultSet = executeQuery("select * from domain_name_log where date_hit = current_date and domain_name = ?",$domainName);
			if ($row = getNextRow($resultSet)) {
				executeQuery("update domain_name_log set hit_count = hit_count + 1 where log_id = ?",$row['log_id']);
			} else {
				$resultSet = executeQuery("insert into domain_name_log (log_id,domain_name,date_hit,hit_count,version) values " .
					"(null,?,now(),1,1)",$domainName);
			}
		}
		if (!empty($_SERVER['HTTP_USER_AGENT'])) {
			$browserInfo = get_browser(null,false);
			$parent = $browserInfo->parent;
			if (strtolower($parent) == "unknown") {
				$parent = "";
			}
			$platform = $browserInfo->platform;
			if (strtolower($platform) == "unknown") {
				$platform = "";
			}
			$platformVersion = $browserInfo->platform_version;
			if (strtolower($platformVersion) == "unknown") {
				$platformVersion = "";
			}
			$browserVersion = $browserInfo->parent;
			if (!empty($platform)) {
				if (!empty($browserVersion)) {
					$browserVersion .= " on " . $platform;
				}
				if (!empty($platformVersion)) {
					$browserVersion .= " " . $platformVersion;
				}
			}
			if (!empty($browserVersion)) {
				$resultSet = executeQuery("select * from browser_log where browser = ?",$browserVersion);
				if ($row = getNextRow($resultSet)) {
					executeQuery("update browser_log set hit_count = hit_count + 1 where log_id = ?",$row['log_id']);
				} else {
					$resultSet = executeQuery("insert into browser_log (log_id,browser,hit_count,version) values " .
						"(null,?,1,1)",$browserVersion);
				}
			}
		}
		
		// add to referer_log
		if (!empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'],$domainName) === false) {
			$trimmedURL = preg_replace('~^(?:https?://)?(?:www[.])?~i', '', $_SERVER['HTTP_REFERER']);
			if (!empty($trimmedURL)) {
				$resultSet = executeQuery("select * from referer_log where dealer_id = ? and date_hit = current_date and referer = ?",$dealerId,$trimmedURL);
				if ($row = getNextRow($resultSet)) {
					executeQuery("update referer_log set hit_count = hit_count + 1 where log_id = ?",$row['log_id']);
				} else {
					$resultSet = executeQuery("insert into referer_log (log_id,dealer_id,referer,date_hit,hit_count) values " .
						"(null,?,?,now(),1)",$dealerId,$trimmedURL);
				}
			}

			// is there a zip-code referral?
			if (!empty($_GET['zip']) && is_numeric($_GET['zip'])) {
				executeQuery("insert into conversion_log (dealer_id,referring_domain,conversion_domain,zip_code,ip_address,time_hit,version) values (?,?,?,?,?,now(),1)",$dealerId,$trimmedURL,$domainName,$_GET['zip'],$_SERVER['REMOTE_ADDR']);
			}

		}
		
		// add to visitor_log
		$hitDate = date('Y-m-d');
		$hitTime = date('His');
		$resultSet = executeQuery("select * from visitor_log where dealer_id = ? and ip_address = ? and visit_date = ? order by visitor_log_id desc",$dealerId,$_SERVER['REMOTE_ADDR'],$hitDate);
		if ($row = getNextRow($resultSet)) {
			// is this hit part of the same session? We'll use 2 hour as a max session time
			if ($hitTime - $row['last_hit'] > 7200) {
				$resultSet = executeQuery("insert into visitor_log (dealer_id,ip_address,visit_date,first_hit,last_hit) values (?,?,?,?,?)",$dealerId,$_SERVER['REMOTE_ADDR'],$hitDate,$hitTime,$hitTime);
			} else {
				$resultSet = executeQuery("update visitor_log set last_hit = ? where visitor_log_id = ?",$hitTime,$row['visitor_log_id']);
			}
		} else {
			$resultSet = executeQuery("insert into visitor_log (dealer_id,ip_address,visit_date,first_hit,last_hit) values (?,?,?,?,?)",$dealerId,$_SERVER['REMOTE_ADDR'],$hitDate,$hitTime,$hitTime);
		}
		

		// add to content_log if we're not in urlalias.php
		if ($gProgramCode != "URLALIAS" && !$gDontRecordHit && $logHit && !$botAgent) {
			if (empty($_SERVER['QUERY_STRING'])) {
				$parameterId = "";
				$parameter = "";
			} else {
				$programArguments = explode("&",$_SERVER['QUERY_STRING']);
				if (strpos($_SERVER['QUERY_STRING'],'search') === false) {					
					$argumentPair = explode("=",$programArguments[0]);
					$parameter = $argumentPair[1];
					switch($argumentPair[0]) {
						case 'category': $parameterId = 1; break;
						case 'department': $parameterId = 2; break;
						case 'product_id': $parameterId = 3; break;
						case 'manufacturer': $parameterId = 4; break;
						default: $parameter = ""; $parameterId = "";
					}
				} else {
					$parameterId = 5;
					// extract the search_for argument
					foreach ($programArguments as $programArgument) {
						if (substr($programArgument,0,10) =='search_for') {
							$argumentPair = explode("=",$programArgument);
							$parameter = $argumentPair[1];
							break;
						}
					}
				}
			}
		                //track dealer data
				$dealerDomain = "www.irunguns.com";
				$systemDealerId = 317;
				if($_SERVER['HTTP_HOST'] == $dealerDomain){
                                    if (!empty($_SERVER['HTTP_USER_AGENT'])) {
					$var = executeQuery("insert into analytics_checker (dealer_id,visit_date,query_string,user_agent) values ($systemDealerId,now(),?,?)",array($_SERVER['REQUEST_URI'],$_SERVER['HTTP_USER_AGENT']));
					}
				}
			// is this program + dealer_id + visit_date already in the log?  if so, increment the hit count
			if (empty($parameter)) {
				$resultSet = executeQuery("select content_log_id from content_log where visit_date = ? and dealer_id = ? and program_id = ?",$hitDate,$dealerId,$programId);
			} else {
				$resultSet = executeQuery("select content_log_id from content_log where visit_date = ? and dealer_id = ? and program_id = ? and parameter_type_id = ? and parameter = ?",$hitDate,$dealerId,$programId,$parameterId,$parameter);
			}
			if ($row = getNextRow($resultSet)) {
				// increment the hit count
				$resultSet = executeQuery("update content_log set hit_count = hit_count + 1 where content_log_id = ?",$row['content_log_id']);
			} else {
				// insert a new record
				$resultSet = executeQuery("insert into content_log (visit_date,dealer_id,program_id,parameter_type_id,parameter,hit_count) values (?,?,?,?,?,1)",$hitDate,$dealerId,$programId,$parameterId,$parameter);				
			}
		}
		if (!empty($_GET['ad-link']) && is_numeric($_GET['ad-link'])) {
      // is this a legitimate advertising id?
			$advertisingId = getFieldFromId('advertising_id','advertising','advertising_id',$_GET['ad-link']);
			$adEndDate = getFieldFromId('end_date','advertising','advertising_id',$_GET['ad-link']);
			$todayDate = date("Y-m-d");
			$mallClick = ($dealerId == 1 ? 1 : 0); // are we on the mall site?
			if ($advertisingId > 0 && $adEndDate >= $todayDate) {
				$resultSet = executeQuery("update advertising set clicks_to_date = clicks_to_date + 1 where advertising_id = ?",$advertisingId);
                                //start Adclicks count table
                                $AdId = getFieldFromId('advertising_id','Adclick_count','advertising_id',$_GET['ad-link']);
                                //$resultSet = executeQuery("select advertising_id,date_clicked from Adclick_count where advertising_id =?",$_GET['ad-link']);
                                //if($row = getNextRow($resultSet)){
                                if($AdId > 0){
                                        $curdate = date('y-m-d');
                                        $result = executeQuery("update Adclick_count set click_count =  click_count + 1 where date_clicked = ? and advertising_id = ? and mall_click = ?",$curdate,$AdId,$mallClick);
                                        if(!$result['affected_rows']){
                                            $click_count =1;
                                            $curdate = date('y-m-d');
                                            $result = executeQuery("insert into Adclick_count (click_count,mall_click,date_clicked,advertising_id) values (?,?,?,?)",$click_count,$mallClick,$curdate,$AdId);
                                        }
                                 }
                                 else {
                                      $click_count =1;
                                      $curdate = date('y-m-d');
                                     $result = executeQuery("insert into Adclick_count (click_count,mall_click,date_clicked,advertising_id) values (?,?,?,?)",$click_count,$mallClick,$curdate,$_GET['ad-link']);                               
                                     
                                     }
                             //End Adclick Count table
                        } 
 
}
			if (!empty($_GET['ban-link']) && is_numeric($_GET['ban-link'])) { 
			$banId = getFieldFromId('banner_id','banners','banner_id',$_GET['ban-link']);
			if($banId > 0)

			 {
                        	// is it from the 'old' banner tracking?
				$bannerId = getFieldFromId('banner_id','banners','banner_id',$_GET['ban-link']);
				$mallClick = ($dealerId == 1 ? 1 : 0); // are we on the mall site?
				if ($bannerId > 0) {
					$resultSet = executeQuery("select log_id from banner_log where banner_id = ? and mall_click = ? and date_hit = ?",$bannerId,$mallClick,$hitDate);
					if ($row = getNextRow($resultSet)) {
						// increment the hit count
						$resultSet = executeQuery("update banner_log set hit_count = hit_count + 1 where log_id = ?",$row['log_id']);
					} else {
						// insert a new record
						$resultSet = executeQuery("insert into banner_log (banner_id,mall_click,date_hit,hit_count) values (?,?,?,1)",$bannerId,$mallClick,$hitDate);		
					}
				}
			}
		}
	}
}

if (is_object($resultSet['result'])) {
	mysqli_free_result($resultSet['result']);
}

function logout() {
	global $gLoggedIn;
	global $gUserId;
	global $gUserName;
	global $gAdministratorFlag;
	global $systemCode;
	$gLoggedIn = false;
	$gUserId = 0;
	$gUserName = "";
	$gUserTypeId = 0;
	$gAdministratorFlag = false;
	if (!array_key_exists($systemCode,$_SESSION)) {
		return;
	}
	foreach ($_SESSION[$systemCode] as $arrayKey => $arrayValue) {
		unset($_SESSION[$systemCode][$arrayKey]);
	}
	unset($_SESSION[$systemCode]);
}

function login($userId) {
	global $gLoggedIn;
	global $gUserId;
	global $gUserTypeId;
	global $gUserName;
	global $gAdministratorFlag;
	global $systemCode;
	$_SESSION[$systemCode] = array();
	$_SESSION[$systemCode]['last_hit'] = time();
	$_SESSION[$systemCode]['user_id'] = $userId;
	$_SESSION[$systemCode]['login_ip_address'] = $_SERVER['REMOTE_ADDR'];
	//$resultSet = executeQuery("select user_name,user_type_id,administrator_flag from users where user_id = ? and inactive = 0",$userId);
	//$resultSet = executeQuery("select user_name,user_type_id,administrator_flag from users where user_id = ?".($_SESSION[$systemCode]['user_id']!=2?" and inactive = 0":""),$userId); Joined contact table for getting logged in user detials
        $resultSet = executeQuery("select * from users left join contacts using (contact_id) where user_id = ?".($_SESSION[$systemCode]['user_id']!=2?" and inactive = 0":""),$userId); 
        if ($row = getNextRow($resultSet)) {
		$gLoggedIn = true;
		$gUserId = $userId;
		$gUserName = $row['user_name']; 
		$gUserTypeId = $row['user_type_id'];
		$gAdministratorFlag = $row['administrator_flag'] == 1;
                $gUserRow = $row;
                
	} else {
		logout();
	}
}
function globalLogin($userName,$dealerId) {
	global $globalLoggedIn;
	global $globalUserName;	
	global $globalSystemCode;
	$_SESSION[$globalSystemCode] = array();
	$_SESSION[$globalSystemCode]['globalUserLastHit'] = time();
	$_SESSION[$globalSystemCode]['global_user_id'] = session_id();
	$_SESSION[$globalSystemCode]['login_ip_address'] = $_SERVER['REMOTE_ADDR']; 
        
        $result = executeQuery("insert into sessions (session_id,session_expires,session_data,dealer_id) values (?,?,?,?)",$_SESSION[$globalSystemCode]['global_user_id'],$_SESSION[$globalSystemCode]['globalUserLastHit'],$userName,$dealerId);
        //$resultSet = executeQuery("select user_name,user_type_id,administrator_flag from users where user_id = ? and inactive = 0",$userId);
	$resultSet = executeQuery("select master_username,dealer_id from dealers where master_username =? and dealer_id = ?",$userName,$dealerId);
        if ($row = getNextRow($resultSet)) {		
                $globalLoggedIn = true;		
		$globalUserName = $row['master_username'];				
	} else {
		globalLogout();
	}
}
function globalLogout() {
	global $globalLoggedIn;
	global $globalUserId;
	global $globalUserTypeId;
	global $globalUserName;	
	global $globalSystemCode;
	$globalLoggedIn = false;
        $globalUserId = 0;
        $globalUserTypeId = 0;
        $globalUserName = '';
        if (!array_key_exists($globalSystemCode,$_SESSION)) {
		return;
	}
        executeQuery("delete from sessions where session_id=?",$_SESSION[$globalSystemCode]['global_user_id']);
	foreach ($_SESSION[$globalSystemCode] as $arrayKey => $arrayValue) {
		unset($_SESSION[$globalSystemCode][$arrayKey]);
	}
	unset($_SESSION[$globalSystemCode]);
}
#################
#getPreference - Get a system preference from system_data
#################
function getPreference($preferenceName) {
	global $gSystemPreferences;
	global $gUserId;
	global $gDealerId;

	$preferenceId = getFieldFromId("preference_id","preferences","preference_code",strtoupper($preferenceName));
	if (empty($preferenceId)) {
		return "";
	}
	if (!array_key_exists($preferenceId,$gSystemPreferences)) {
		$result = executeQuery("select * from user_preferences where preference_id = ? and user_id = ?",$preferenceId,$gUserId);
		if ($row = getNextRow($result)) {
			$gSystemPreferences[$preferenceId] = $row['system_value'];
		} else {
			$result = executeQuery("select * from dealer_preferences where preference_id = ? and dealer_id = ?",$preferenceId,$gDealerId);
			if ($row = getNextRow($result)) {
				$gSystemPreferences[$preferenceId] = $row['system_value'];
			} else {
				$result = executeQuery("select * from system_preferences where preference_id = ?",$preferenceId);
				if ($row = getNextRow($result)) {
					$gSystemPreferences[$preferenceId] = $row['system_value'];
				} else {
					$gSystemPreferences[$preferenceId] = "";
				}
			}
		}
	}
	return $gSystemPreferences[$preferenceName];
}

#################
#getUserFullName - Get the user's name
#################
function getUserFullName($userId="") {
	global $gLoggedIn;
	global $gUserId;

	if (empty($userId)) {
		$userId = $gUserId;
	}
	if (!$gLoggedIn) {
		return "Unknown";
	}
	$fullName = "";
	$resultSet = executeQuery("select first_name,last_name from users where user_id = ?",$userId);
	if ($row = getNextRow($resultSet)) {
		$fullName = $row['first_name'];
		if (!empty($fullName) && !empty($row['last_name'])) {
			$fullName .= " ";
		}
	}
	return $fullName;
}

#################
#showSignificant - Format a number, showing only significant digits
#################
function showSignificant($input,$decimals=0,$showComma=false,$leaveEmpty=false) {
	if ($leaveEmpty && strlen($input) == 0) {
		return $input;
	}
	if (empty($input)) {
		$input = "0";
	}
	$significantNumber = number_format($input,10,".",($showComma ? "," : ""));
	if (empty($significantNumber)) {
		$significantNumber = "0";
	}
	$foundDecimal = false;
	while ((!empty($significantNumber) && !$foundDecimal && substr($significantNumber,-1) == "0") || substr($significantNumber,-1) == ".") {
		if (!$foundDecimal) {
			$foundDecimal = substr($significantNumber,-1) == ".";
		}
		$significantNumber = substr($significantNumber,0,strlen($significantNumber) - 1);
	}
	if (empty($significantNumber)) {
		$significantNumber = "0";
	}
	if (!empty($decimals)) {
		if (strpos($significantNumber,".") === false) {
			$significantNumber .= ".";
		}
		while (strlen($significantNumber) < (strpos($significantNumber,".") + $decimals + 1)) {
			$significantNumber .= "0";
		}
		while (strlen($significantNumber) < (strpos($significantNumber,".") + $decimals + 1)) {
			$significantNumber .= "0";
		}
		while (strpos($significantNumber,".") < (strlen($significantNumber) - $decimals - 1)) {
			$significantNumber .= substr($signficantNumber,0,strlen($significantNumber) - 1);
		}
	}
	return $significantNumber;
}

#################
#makeParameter - takes a string value and turns it into a value to be used in a sql statement
#################
function makeParameter($input) {
	$input = trim($input,"\0 \t");
	if (empty($input) && strlen($input) == 0) {
		return "";
	}
	return ("'" . $GLOBALS['gDBConnection']->real_escape_string($input) . "'");
}

#################
#makeDateParameter - takes a date value and turns it into a value to be used in a sql statement
#################
function makeDateParameter($input) {
	$dateString = strtotime($input);
	if ($dateString === false || empty($input) || strlen($input) == 0) {
		return "";
	}
	return date("Y-m-d",$dateString);
}

#################
#makeDatetimeParameter - takes a date & time value and turns it into a value to be used in a sql statement
#################
function makeDatetimeParameter($dateValue,$timeValue="") {
	$dateString = strtotime($dateValue);
	if (!empty($timeValue)) {
		$timeString = strtotime($timeValue);
	} else {
		$timeString = strtotime($dateValue);
	}
	if ($dateString === false || $timeString === false || empty($dateValue) || strlen($dateValue) == 0) {
		return "";
	}
	$dateValue = date("Y-m-d",$dateString);
	if (empty($timeValue)) {
		$timeValue = "00:00:00";
	} else {
		$timeValue = date("H:i:s",$timeString);
	}
	return $dateValue . " " . $timeValue;
}

#################
#makeTimeParameter - takes a time formatted value and turns it into a float value (number of hours)
#################
function makeTimeParameter($timeValue) {
	if (empty($timeValue)) {
		return "";
	}
	$timeParts = explode(":",$timeValue);
	$timeValue = $timeParts[0] * 1 + (count($timeParts) > 1 ? $timeParts[1]/60 : 0) + (count($timeParts) > 2 ? $timeParts[2]/3600 : 0);
	return $timeValue;
}

#################
#makeNumberParameter - takes a number value and turns it into a value to be used in a sql statement
#################
function makeNumberParameter($input) {
	$newNumber = "";
	$negativeValue = false;
	$decimalValue = false;
	for ($x=0;$x<strlen($input);$x++) {
		if (substr($input,$x,1) >= "0" && substr($input,$x,1) <= "9") {
			$newNumber .= substr($input,$x,1);
		}
		if (substr($input,$x,1) == "-") {
			$negativeValue = true;
		}
		if (substr($input,$x,1) == "." && !$decimalValue) {
			$decimalValue = true;
			$newNumber .= ".";
		}
	}
	if (strlen($newNumber) == 0) {
		return "";
	}
	if ($negativeValue) {
		$newNumber = "-" . $newNumber;
	}
	if ($newNumber == "-" || $newNumber == "." || $newNumber == "-.") {
		$newNumber = "0";
	}
	return $newNumber;
}

#################
#parseNumber - takes a number value and turns it into a value to be used for calculations
#################
function parseNumber($input) {
	$newNumber = "";
	for ($x=0;$x<strlen($input);$x++) {
		if ((substr($input,$x,1) >= "0" && substr($input,$x,1) <= "9") || substr($input,$x,1) == ".") {
			$newNumber .= substr($input,$x,1);
		}
	}
	if (strlen($newNumber) == 0) {
		return 0;
	}
	$newNumber = $newNumber - 0;
	return $newNumber;
}

################
#getFieldFromId - get a field using an ID
################
function getFieldFromId($fieldName,$tableName,$idName,$primaryId,$extraWhere = "") {
	$field = "";
	if (!empty($primaryId)) {
		$resultSet = executeQuery("select $fieldName from $tableName where $idName = ?" . (empty($extraWhere) ? "" : " and " . $extraWhere),$primaryId);
		if ($row = getNextRow($resultSet)) {
			$field = $row[$fieldName];
		}
	}
	return $field;
}

################
#logError - Log an error that happened
################
$lastErrorLog = "";
$loggedErrors = array();
function logError($errorText,$errorQuery="",$parameters = array()) {
return;
	global $lastErrorLog;
	global $loggedErrors;

	if (!is_array($loggedErrors)) {
		$loggedErrors = array();
	}
	if (in_array($errorText,$loggedErrors)) {
		return;
	}
	$loggedErrors[] = $errorText;
	if (empty($errorQuery)) {
		$errorQueryParameter = "NULL";
	} else {
		$errorQueryParameter = "'" . addslashes($errorQuery) . "'";
	}

	$emailText = "<html>\n<body>\n";
	$emailText .= "<p>An error as occurred on " . date("m/d/y g:ia") . " at " . $_SERVER['SERVER_ADDR'] . "</p>\n";
	$emailText .= "<p>The error message is as follows: " . $errorText . ($errorQuery == "NULL" ? "" : "</p>\n<p>The query that produced the error is: " .
		$errorQuery) . "</p>\n";
	$emailText .= "<p>Backtrace is:</p>\n";
	$backtrace = debug_backtrace(false);
	foreach ($backtrace as $thisBacktrace) {
		$args = "";
		if (isset($thisBacktrace['args']) && is_array($thisBacktrace['args'])) {
			foreach ($thisBacktrace['args'] AS &$arg) {
				if (is_object($arg)) {
					$arg = 'CONVERTED OBJECT OF CLASS ' . get_class($arg);
				}
			}
		}
        $function = $thisBacktrace['function'] . '(' . (isset($thisBacktrace['args']) && is_array($thisBacktrace) ? implode(", ",$thisBacktrace['args']) : "") . ')';
        $emailText .= "<p>Function: " . $function . ", line: " . $thisBacktrace['line'] . ", file: " . $thisBacktrace['file'] . ", class: " . $thisBacktrace['class'] . ", type: " . $thisBacktrace['type'] . "</p>\n";
        $errorText .= "\n\nFunction: " . $function . ", line: " . $thisBacktrace['line'] . ", file: " . $thisBacktrace['file'] . ", class: " . $thisBacktrace['class'] . ", type: " . $thisBacktrace['type'];
	}
	if (count($parameters) > 0) {
		$emailText .= "<p>Parameters:</p>\n";
		$errorText .= "\n\nParameters:\n\n";
		foreach ($parameters as $fieldName => $fieldValue) {
			$emailText .= "<p>" . $fieldName . ": " . $fieldValue . "</p>\n";
			$errorText .= $fieldName . ": " . $fieldValue . "\n";
		}
	}
	$emailText .= "</body></html>";

	$query = "insert into error_log (log_id,user_id,error_time,program_name,error_message,query_text,version) values (null," . (empty($GLOBALS['gUserId']) ? "null" : $GLOBALS['gUserId']) .
		",now()," . makeParameter($_SERVER['SCRIPT_NAME']) . "," . makeParameter($errorText) . ",$errorQueryParameter,1)";
	$lastErrorLog = $query;
	$GLOBALS['gDBConnection']->query($query);
	$thisErrorText = $GLOBALS['gDBConnection']->error;
	if (!empty($thisErrorText)) {
		syslog(LOG_ERR,$thisErrorText . ": " . $query);
	}

	$sendToAddresses = array("mallnfdn@gmail.com");
	$emailAdded = false;
	foreach($sendToAddresses as $emailAddress) {
		if (empty($emailAddress)) {
			continue;
		}
		$emailAddresses[] = $emailAddress;
		$emailAdded = true;
	}
	if ($emailAdded) {
		$errorMessage = sendEmail("Error log entry created",$emailText,$emailAddresses);
		if (!empty($errorMessage)) {
			$query = "insert into error_log (log_id,user_id,error_time,program_name,error_message,query_text,version) values (null,{$GLOBALS['gUserId']},now()," .
				makeParameter($_SERVER['SCRIPT_NAME']) . ",'" . addslashes($errorMessage) . "',null,1)";
			$GLOBALS['gDBConnection']->query($query);
		}
	}
}

###############
#sendEmail - send an email
###############
function sendEmail($subject,$body,$emailAddresses) {
	$mail = new PHPMailer();
	$mail->IsMail();
	$mail->IsHTML(true);
	$mail->SetFrom("web@nfdnetwork.com", "NFDN System");
	$mail->AddReplyTo("web@nfdnetwork.com", "NFDN System");
	foreach ($emailAddresses as $emailAddress) {
		if (!empty($emailAddress)) {
			if ($emailAdded) {
				$mail->AddCC($emailAddress);
			} else {
				$mail->AddAddress($emailAddress);
			}
			$emailAdded = true;
		}
	}
	$mail->Subject = $subject;
	$mail->Body = $body;
	$errorMessage = "";
	if ($emailAdded) {
		if(!$mail->Send()) {
			$errorMessage = $mail->ErrorInfo;
		}
	} else {
		$errorMessage = "Nothing sent";
	}
	return $errorMessage;
}
function registerContact($contactId) {
	$contactName = getFieldFromId('company_name','contacts','contact_id',$contactId);
	$prefId = getFieldFromId('preference_id','preferences','preference_code','EMAIL_REGISTER_LOG');
	$email = getFieldFromId('preference_value','system_preferences','preference_id',$prefId);
	sendEmail('Register',$contactName,array($email));
}

###############
# startTransaction - start a transaction
###############
function startTransaction() {
	$GLOBALS['gLastErrorLog'] = "";
	return executeQuery("start transaction");
}

###############
# rollbackTransaction - rollback a transaction
###############
function rollbackTransaction() {
	$query = "rollback";
	$GLOBALS['gDBConnection']->query($query);
	if (!empty($GLOBALS['gLastErrorLog'])) {
		$GLOBALS['gDBConnection']->query($GLOBALS['gLastErrorLog']);
	}
	$GLOBALS['gLastErrorLog'] = "";
}

###############
# commitTransaction - commit a transaction
###############
function commitTransaction() {
	return executeQuery("commit");
}

###############
#prepareStatement - prepare and return an SQL statement, which can later be bound to variables and executed
###############
function prepareStatement($query) {
	return executeQuery($query,array(),true);
}

###############
#executeStatement - bind data to a previously prepared statement and execute it
###############
function executeStatement($statement,$parameters) {
	if (!is_array($parameters)) {
		$parameters = func_get_args();
		unset($parameters[0]);
	}
	return executeQuery($statement['statement'],$parameters,false,$statement['query']);
}

###############
#executeQuery - execute a query that returns a result set
###############
function executeQuery($query,$parameters = array(),$prepareOnly = false,$statementQuery = "") {
	$executeOnly = (is_object($query) && get_class($query) == "mysqli_stmt");
	$resultSet = array();
	if ($GLOBALS['gDebug']) {
		echo $query . "<br/>";
	}
	if (!is_array($parameters)) {
		$parameters = func_get_args();
		unset($parameters[0]);
		$prepareOnly = false;
		$statementQuery = "";
	}
	if ((!is_array($parameters) || count($parameters) == 0) && !$prepareOnly && !$executeOnly) {
		$result = $GLOBALS['gDBConnection']->query($query);
	} else {
		$types = "";
		foreach($parameters as $index => $parameter) {
			if (strlen(trim($parameter,"\0 \t")) == 0) {
				$parameters[$index] = null;
				$types .= "s";
			} else if (is_int($parameter)) {
				$types .= 'i';
			} else if (is_float($parameter)) {
				$types .= 'd';
			} else if (is_string($parameter)) {
				$types .= 's';
			} else {
				$types .= 'b';
			}
		}
		$parameterReferences = array();
		$parameterReferences[] = $types;
		foreach ($parameters as $index => $parameter) {
			$parameterReferences[] = &$parameters[$index];
		}
		if (!$executeOnly) {
			$statement = $GLOBALS['gDBConnection']->prepare($query);
		} else {
			$statement = $query;
			$query = $statementQuery;
		}
		if ($statement && !$prepareOnly) {
			if (call_user_func_array(array($statement, 'bind_param'), $parameterReferences)) {
				$statement->execute();
				$result = $statement->get_result();
			}
		}
		$resultSet['parameters'] = $parameterReferences;
	}
	$resultSet['query'] = $query;
	$resultSet['sql_error'] = $GLOBALS['gDBConnection']->error;
	$resultSet['sql_error_number'] = $GLOBALS['gDBConnection']->errno;
	$resultSet['affected_rows'] = $GLOBALS['gDBConnection']->affected_rows;
	$resultSet['insert_id'] = $GLOBALS['gDBConnection']->insert_id;
	if (!$prepareOnly && !$executeOnly && is_object($statement) && get_class($statement) == "mysqli_stmt") {
		$statement->close();
	}
	if ($prepareOnly) {
		$resultSet['statement'] = $statement;
	} else {
		$resultSet['result'] = $result;
		if (is_object($result)) {
			$resultSet['row_count'] = $result->num_rows;
		}
	}
	if (!empty($resultSet['sql_error']) && !$GLOBALS['gDontLogError']) {
		logError($resultSet['sql_error'],$query,$parameters);
	}
	
	return $resultSet;
}
###############
#getNumRow - get the number of rows, using a result from a previous query
###############
function getNumRows($resultSet) {
	if (!is_object($resultSet['result'])) {
		return array();
	}
	if ($rows = $resultSet['result']->num_rows) {
		return $rows;
	}
	return array();
}
###############
#getNextRow - get the next row, using a result from a previous query
###############
function getNextRow($resultSet) {
	if (!is_object($resultSet['result'])) {
		return array();
	}
	if ($row = $resultSet['result']->fetch_assoc()) {
		return $row;
	}
	return array();
}

#################
#getUserDisplayName - Get the user's name
#################
function getUserDisplayName($userId="") {
	global $gLoggedIn;
	global $gUserId;

	if (empty($userId)) {
		$userId = $gUserId;
	}
	return getDisplayName(getFieldFromId("contact_id","users","user_id",$userId));
}

################
#getDisplayName - function to construct the full name & company for display
################
function getDisplayName($contactId) {
	$displayName = "";
	if (!empty($contactId)) {
		$resultSet = executeQuery("select first_name,last_name from contacts where contact_id = ?",$contactId);
		if ($row = getNextRow($resultSet)) {
			$displayName = $row['first_name'];
			if (!empty($displayName) && !empty($row['last_name'])) {
				$displayName .= " ";
			}
			$displayName .= $row['last_name'];
		}
	}
	return $displayName;
}

################
#getCrc32 - get CRC value for a string. Used by client to check to see if data changed.
################
function getCrc32($value) {
	$value = str_replace("\r\n","\n",$value);
	$newValue = "";
	for ($x=0;$x<strlen($value);$x++) {
		$asciiValue = ord(substr($value,$x,1));
		if ($asciiValue > 122) {
			continue;
		}
		if ($asciiValue < 32) {
			$newValue .= " ";
		} else {
			$newValue .= substr($value,$x,1);
		}
	}
	$crc32Value = dechex(crc32($newValue));
	while (strlen($crc32Value) < 8) {
		$crc32Value = "0" . $crc32Value;
	}
	return "#" . strtoupper($crc32Value);
}

################
#createChangeLog - takes an old value and new value and, if they are different, makes an entry into
#				   change_log for that table and row
################
function createChangeLog($tableName,$fieldName,$keyValue,$oldValue,$newValue) {
	global $gUserId;
	if (strcmp(trim($oldValue),trim($newValue)) != 0 || !(strpos($fieldName,"Row ") === false)) {
		executeQuery("insert into change_log (log_id,user_id,table_name,column_name,primary_key,time_changed,old_value,new_value,version) values " .
			"(null,?,?,?,?,now(),?,?,1)",$gUserId,$tableName,$fieldName,$keyValue,$oldValue,$newValue);
		return true;
	}
	return false;
}

################
#createNumberChangeLog - takes an old value and new value and, if they are different, makes an entry into
#				   change_log for that table and row
################
function createNumberChangeLog($tableName,$fieldName,$keyValue,$oldValue,$newValue) {
	$oldValue = makeNumberParameter($oldValue);
	$newValue = makeNumberParameter($newValue);
	if ($oldValue != $newValue) {
		return createChangeLog($tableName,$fieldName,$keyValue,$oldValue,$newValue);
	} else {
		return false;
	}
}

################
#createBooleanChangeLog - takes an old value and new value and, if they are different, makes an entry into
#					   change_log for that table and row
################
function createBooleanChangeLog($tableName,$fieldName,$keyValue,$oldValue,$newValue) {
	$oldValue = $oldValue . "";
	$newValue = $newValue . "";
	$oldValue = ($oldValue == "1" || $oldValue == "Y" ? "true" : "false");
	$newValue = ($newValue == "1" || $newValue == "Y" ? "true" : "false");
	return createChangeLog($tableName,$fieldName,$keyValue,$oldValue,$newValue);
}

################
#createDateChangeLog - takes an old value and new value and, if they are different, makes an entry into
#					   change_log for that table and row
################
function createDateChangeLog($tableName,$fieldName,$keyValue,$oldValue,$newValue) {
	if (!empty($oldValue)) {
		$oldValue = date("m/d/Y",strtotime($oldValue));
	}
	if (!empty($newValue)) {
		$newValue = date("m/d/Y",strtotime($newValue));
	}
	return createChangeLog($tableName,$fieldName,$keyValue,$oldValue,$newValue);
}

################
#createDatetimeChangeLog - takes an old value and new value and, if they are different, makes an entry into
#					   change_log for that table and row
################
function createDatetimeChangeLog($tableName,$fieldName,$keyValue,$oldValue,$newValue) {
	if (!empty($oldValue)) {
		$oldValue = date("m/d/Y g:ia",strtotime($oldValue));
	}
	if (!empty($newValue)) {
		$newValue = date("m/d/Y g:ia",strtotime($newValue));
	}
	return createChangeLog($tableName,$fieldName,$keyValue,$oldValue,$newValue);
}


################
# functions below added by zipperSNAP
################

###############
# getRandomString - get a random string used as the seed for the password encoding
#
###############
function getRandomString() {
	return md5(uniqid(rand(), true));
}

#################
#getDealerInfo - Get the dealer info and return an array
#################
function getDealerInfo($dealerId=0) {
	global $gDealerId;
	global $gDefaultDealerId;
	if (empty($dealerId)) {
		$dealerId = $gDealerId;
	}
	if ($dealerId > 0) {
		$thisDealer = $dealerId;
	} else {
		$thisDealer = $gDefaultDealerId;
	}
	$dealerInfo = array();
	$resultSet = executeQuery("select d.dealer_id,d.ffl_number,d.ignore_exceptions,d.extra_code,c.company_name,c.first_name,c.last_name,c.address_1,c.city,c.state,c.zip_code,c.email_address," .
		"c.phone_number,c.phone_number_2,c.fax_number,d.facebook_link,d.twitter_link,d.development_access,d.extra_code,d.use_nfdn_shipping,d.enable_global_login,d.enable_commonwealth,d.allow_dealer_promotion,d.cw_min_purchase_amt " .
		"from dealers d left join contacts c using (contact_id) where d.dealer_id = ?",$thisDealer);
	if ($row = getNextRow($resultSet)) {
		$dealerInfo['dealer_id'] = $thisDealer;
		$dealerInfo['ffl_number'] = $row['ffl_number'];
		$dealerInfo['ignore_exceptions'] = $row['ignore_exceptions'];
		$dealerInfo['dealer_name'] = $row['company_name'];
		$dealerInfo['dealer_address'] = $row['address_1'];
		$dealerInfo['dealer_city'] = $row['city'];
		$dealerInfo['first_name'] = $row['first_name'];
		$dealerInfo['last_name'] = $row['last_name'];
		$dealerInfo['dealer_state'] = $row['state'];
		$dealerInfo['dealer_zip_code'] = $row['zip_code'];
		$dealerInfo['dealer_email'] = strip_tags($row['email_address']);
		$dealerInfo['phone_number'] = strip_tags($row['phone_number']);
		$dealerInfo['phone_number_2'] = strip_tags($row['phone_number_2']);
		$dealerInfo['fax_number'] = strip_tags($row['fax_number']);
		$dealerInfo['facebook_link'] = strip_tags($row['facebook_link']);
		$dealerInfo['twitter_link'] = strip_tags($row['twitter_link']);
		$dealerInfo['extra_code'] = $row['extra_code'];
		$dealerInfo['use_nfdn_shipping'] = $row['use_nfdn_shipping'];
		$dealerInfo['development_access'] = $row['development_access'];
		$dealerInfo['dealer_url'] = $GLOBALS['gDomain'];
                $dealerInfo['enable_global_login'] = $row['enable_global_login'];
                $dealerInfo['enable_commonwealth'] = $row['enable_commonwealth'];
                $dealerInfo['cw_min_purchase_amt'] = $row['cw_min_purchase_amt'];
                $dealerInfo['allow_dealer_promotion'] = $row['allow_dealer_promotion'];
		// TODO -- iterate through a result and set array key-value pairs based on preference_code
		$dealerInfo['logo_image_id'] = getFieldFromId('preference_value','dealer_preferences','dealer_id',$thisDealer,'preference_id = 1');
		$dealerInfo['store_hours'] = getFieldFromId('preference_value','dealer_preferences','dealer_id',$thisDealer,'preference_id = 2');
		$dealerInfo['sidebar_extra'] = getFieldFromId('preference_value','dealer_preferences','dealer_id',$thisDealer,'preference_id = 16');
		$dealerInfo['use_mall_features'] = getFieldFromId('preference_value','dealer_preferences','dealer_id',$thisDealer,'preference_id = 17');	
                $dealerInfo['pinterest'] = getFieldFromId('preference_value','dealer_preferences','dealer_id',$thisDealer,'preference_id = 36');
	}
	//store a set of allowed distributor ids in preference order for this dealer
	$distributorArray = array();
	$distributorAutoOrderArray = array();
	$dealerInfo['dealerDistributors'] = array();
	$query  = "select * from dealer_distributors dd left join distributors d using (distributor_id) ";
	$query .= "where dd.dealer_id = ? and dd.inactive = 0 and d.internal_use_only = 0 and d.inactive = 0 order by dd.priority";
	$resultSet = executeQuery($query,$thisDealer);
	while ($row = getNextRow($resultSet)) {
		// need to store each dealer's full login credentials for each distributor
		$dealerInfo['dealerDistributors'][] = $row;
		$distributorArray[] = $row['distributor_id'];
		if ($row['auto_order'] == 1) {
			$distributorAutoOrderArray[] = $row['distributor_id'];
		}
	}
	$dealerInfo['distributorSet'] = implode(",",$distributorArray);
	$dealerInfo['distributorsAutoOrder'] = $distributorAutoOrderArray;
	
	// get this dealer's retail price preference
	$preferenceId = getFieldFromId('preference_id','preferences','preference_code','USE_RETAIL_PRICE');
	$resultSet = executeQuery("select * from dealer_preferences where preference_id = ? and dealer_id = ?",$preferenceId,$dealerId);
	if ($row = getNextRow($resultSet)) {
		$dealerInfo['use_retail_price'] = ($row['preference_value'] == "Y" ? 1 : 0);
	} else {
		$dealerInfo['use_retail_price'] = 0;
	}

	// pull these from dealer preferences when set up!
	$dealerInfo['site_description'] = "America's largest online firearms and accessories mall.";
	$dealerInfo['site_keywords'] = "assault rifles, machine guns, gun dealer, firearms, firearms dealer, firearms";
	//

	return $dealerInfo;	
}

#################
#getTemplateInfo - Set template info based on dealerId
#################
function getTemplateInfo($dealerId,$forceTemplateId = 0) {
	if (empty($dealerId)) {
		$dealerId = $GLOBALS["gDealerId"];
	}
	$templateArray = array();
	if ($forceTemplateId > 0) {
		$templateArray['id'] = $forceTemplateId;
	} else {
		$templateArray['id'] = getFieldFromId('template_id','dealers','dealer_id',$dealerId);
	}
	$templateArray['default'] = "templates/default";
	if (empty($templateArray['id'])) {
		$templateArray['path'] = $templateArray['default'];
	} else {
		$templateArray['path'] = "templates/" . getFieldFromId('template_path','templates','template_id',$templateArray['id']);
	}
	if (file_exists($templateArray['path'] . "/header.inc")) {
		$templateArray['header'] = $templateArray['path'];
	} else {
		$templateArray['header'] = $templateArray['default']; 
	}
	if (file_exists($templateArray['path'] . "/sidebar.inc")) {
		$templateArray['left'] = $templateArray['path'];
	} else {
		$templateArray['left'] = $templateArray['default']; 
	}
	if (file_exists($templateArray['path'] . "/right.inc")) {
		$templateArray['right'] = $templateArray['path'];
	} else {
		$templateArray['right'] = $templateArray['default']; 
	}
	if (file_exists($templateArray['path'] . "/footer.inc")) {
		$templateArray['footer'] = $templateArray['path'];
	} else {
		$templateArray['footer'] = $templateArray['default']; 
	}
	if (file_exists($templateArray['path'] . "/functions.inc")) {
		$templateArray['functions'] = $templateArray['path'];
	} else {
		$templateArray['functions'] = $templateArray['default']; 
	}
	if (file_exists($templateArray['path'] . "/homebanners.css")) {
		$templateArray['homebanners_css'] = $templateArray['path'];
	} else {
		$templateArray['homebanners_css'] = $templateArray['default']; 
	}
	if (file_exists($templateArray['path'] . "/homebanners.inc")) {
		$templateArray['homebanners'] = $templateArray['path'];
	} else {
		$templateArray['homebanners'] = $templateArray['default']; 
	}
	if (file_exists($templateArray['path'] . "/catalogbanners-c.inc")) {
		$templateArray['catalogbanners'] = $templateArray['path'];
	} else {
		$templateArray['catalogbanners'] = $templateArray['default']; 
	}
	if (file_exists($templateArray['path'] . "/zipcode_finder.css")) {
		$templateArray['zipcodes'] = $templateArray['path'];
	} else {
		$templateArray['zipcodes'] = $templateArray['default']; 
	}
	if (file_exists($templateArray['path'] . "/checkout.css")) {
		$templateArray['checkout_styles'] = $templateArray['path'];
	} else {
		$templateArray['checkout_styles'] = $templateArray['default']; 
	}
	if (file_exists($templateArray['path'] . "/checkout_sidebar.inc")) {
		$templateArray['checkout_sidebar'] = $templateArray['path'];
	} else {
		$templateArray['checkout_sidebar'] = $templateArray['default']; 
	}
	return $templateArray;
}

################
#getProductInfo - takes a product_id and returns all info required for catalog display
################
function getProductInfo($productId,$dealerId) {
	//global $gDistributorSet;
	global $dealerArray;
	$productArray = array();
	if (!empty($productId)) {
		$resultSet = executeQuery("select * from products where product_id = ?",$productId);
		if ($row = getNextRow($resultSet)) {
			$productArray['product_id'] = $productId;
			$productArray['store_item'] = !empty($row['dealer_id']);
			$productArray['description'] = $row['description'];
			$productArray['detailed_description'] = $row['detailed_description'];
			$productArray['model'] = $row['model'];
			$productArray['retail_price'] = $row['retail_price'];
			$productArray['map_price'] = $row['map_price'];
			$productArray['upc'] = $row['upc'];
			$productArray['category_id'] = $row['category_id'];
			$productArray['category_name'] = getFieldFromId('description','categories','category_id',$row['category_id']);
			$productArray['department_id'] = getFieldFromId('department_id','categories','category_id',$row['category_id']);
			$productArray['department_name'] = getFieldFromId('description','departments','department_id',$productArray['department_id']);
			$productArray['manufacturer_id'] = $row['manufacturer_id'];
			$productArray['manufacturer_name'] = getFieldFromId('description','manufacturers','manufacturer_id',$row['manufacturer_id']);
			if (!empty($row['caliber_id'])) {
				$productArray['caliber_id'] = $row['caliber_id'];
				$productArray['caliber_name'] = getFieldFromId('description','calibers','caliber_id',$row['caliber_id']);
			}
			$productArray['image_id'] = $row['image_id'];
			$productArray['thumbnail_image_id'] = $row['thumbnail_image_id'];
			$productArray['inactive'] = $row['inactive'];
			$productArray['is_firearm'] = $row['is_firearm'];
			$productArray['shipping_weight'] = $row['shipping_weight'];
			$productArray['non_taxable'] = $row['non_taxable'];
			if ($productArray['store_item']) {
				$resultSet = executeQuery("select * from dealer_product_data where product_data_type = 1 and dealer_id = ? and product_id = ?",$dealerId,$productId);
				$row = getNextRow($resultSet);
				$productArray['total_quantity'] = (empty($row['quantity_in_stock'])?0:$row['quantity_in_stock']);
				$productArray['available_quantity'] = $productArray['total_quantity'];
				$productArray['available_dealer_quantity'] = $productArray['total_quantity'];
				$productArray['distributor_id'] = 0;
				$productArray['dealer_cost'] = $row['store_price'];
			} else {
				// find total quantity from dealer distributors that carry this product
				$distributorArray = array();
				$quantity = 0;
				$allocated = 0;
				$quantityForAutoOrder = 0;
				$dealerCost = 0;
				$query  = "select * from distributor_inventory where product_id = ? and quantity > 0";
				if (!empty($dealerArray['distributorSet'])) {
					$query .= " and distributor_id in ({$dealerArray['distributorSet']})";
					$query .= " order by find_in_set(distributor_id, '{$dealerArray['distributorSet']}')";
				}
				$resultSet = executeQuery($query,$productId);
				while ($row = getNextRow($resultSet)) {
					// store first distributor info as a fallback in case get_price.php fails
					if (empty($productArray['distributor_id'])) {
						$productArray['distributor_id'] = $row['distributor_id'];
						$productArray['dealer_cost'] = $row['dealer_cost'];
						// does this distributor offer live price?
						foreach ($dealerArray['dealerDistributors'] as $distributorRow) {
							if ($distributorRow['distributor_id'] == $productArray['distributor_id']) {
								$productArray['distributor_live_price'] = $distributorRow['uses_live_price'];
								break;
							}
						}
					}

					$instaClickEnabled = false;
					if (count($dealerArray['distributorsAutoOrder']) > 0 && in_array($row['distributor_id'],$dealerArray['distributorsAutoOrder'])) {
						$instaClickEnabled = true;
					}

					// allocated stock is only available to insta-click enabled dealers
					// and is limited to 1 per customer
					// $quantityForAutoOrder is used in the admin apps for assembly orders
					if ($row['allocated'] > 0) {
						$allocated += $row['allocated'];
						$quantity += $row['quantity'] - $row['allocated'] + ($instaClickEnabled ? 1 : 0);
						$quantityForAutoOrder += $row['quantity'] - $row['allocated'];
					} else {
						$quantity += $row['quantity'];
						$quantityForAutoOrder += ($instaClickEnabled ? $row['quantity'] : 0);
					}

					$dealerCost += $row['dealer_cost'];
					$distributorArray[] = $row['distributor_id'];
				}
				$productArray['total_quantity'] = $quantity;
				$productArray['total_allocated'] = $allocated;
				$productArray['average_dealer_cost'] = (count($distributorArray)>0 ? round($dealerCost / count($distributorArray),2) : 0);
				$productArray['distributors'] = $distributorArray;
				$resultSet = executeQuery("select sum(quantity) as quantity from order_items left join orders using (order_id) where product_id = ? and order_date = ?",$productId,date('Y-m-d'));
				$row = getNextRow($resultSet);
				$productArray['pending_orders'] = $row['quantity'];
				
				$productArray['available_dealer_quantity'] = $productArray['total_quantity'] - $productArray['total_allocated'] - $productArray['pending_orders'];
				$productArray['available_quantity'] = $productArray['available_dealer_quantity']  + ($productArray['total_allocated'] > 0 ? 1 : 0);
				
				$productArray['available_for_auto_order'] = $quantityForAutoOrder;
			}
			if ($productArray['available_quantity'] > 0 && $productArray['available_quantity'] < 6) {
				$productArray['low_quantity'] = true; // used for alerts on catalog and checkout pages
			}
		}
	}
	return $productArray;
}

################
#getProductInfoMin - takes a product_id and returns minimum info required for catalog display
################
function getProductInfoMin($productId,$dealerId) {
	//global $gDistributorSet;
	global $dealerArray;
	$productArray = array();
	if (!empty($productId)) {
		$resultSet = executeQuery("select * from products where product_id = ?",$productId);
		if ($row = getNextRow($resultSet)) {
			$productArray['product_id'] = $productId;
			$productArray['store_item'] = !empty($row['dealer_id']);
			$productArray['category_id'] = $row['category_id'];
			$productArray['category_name'] = getFieldFromId('description','categories','category_id',$row['category_id']);
			$productArray['department_id'] = getFieldFromId('department_id','categories','category_id',$row['category_id']);
			$productArray['department_name'] = getFieldFromId('description','departments','department_id',$productArray['department_id']);
			$productArray['description'] = $row['description'];
			$productArray['retail_price'] = $row['retail_price'];
			$productArray['map_price'] = $row['map_price'];
			$productArray['upc'] = $row['upc'];
			$productArray['thumbnail_image_id'] = $row['thumbnail_image_id'];
			$productArray['is_firearm'] = $row['is_firearm'];
			$productArray['shipping_weight'] = $row['shipping_weight'];
			$productArray['non_taxable'] = $row['non_taxable'];
			if ($productArray['store_item']) {
				$resultSet = executeQuery("select * from dealer_product_data where product_data_type = 1 and dealer_id = ? and product_id = ?",$dealerId,$productId);
				$row = getNextRow($resultSet);
				$productArray['total_quantity'] = (empty($row['quantity_in_stock'])?0:$row['quantity_in_stock']);
				$productArray['available_quantity'] = $productArray['total_quantity'];
				$productArray['distributor_id'] = 0;
				$productArray['dealer_cost'] = $row['store_price'];
			} else {
				// find total quantity from dealer distributors that carry this product
				$distributorArray = array();
				$quantity = 0;
				$allocated = 0;
				$quantityForAutoOrder = 0;
				$dealerCost = 0;
				$query  = "select * from distributor_inventory where product_id = ? and quantity > 0";
				if (!empty($dealerArray['distributorSet'])) {
					$query .= " and distributor_id in ({$dealerArray['distributorSet']})";
					$query .= " order by find_in_set(distributor_id, '{$dealerArray['distributorSet']}')";
				}
				$resultSet = executeQuery($query,$productId);
				while ($row = getNextRow($resultSet)) {
					// store first distributor info as a fallback in case get_price.php fails
					if (empty($productArray['distributor_id'])) {
						$productArray['distributor_id'] = $row['distributor_id'];
						$productArray['dealer_cost'] = $row['dealer_cost'];
						// does this distributor offer live price?
						foreach ($dealerArray['dealerDistributors'] as $distributorRow) {
							if ($distributorRow['distributor_id'] == $productArray['distributor_id']) {
								$productArray['distributor_live_price'] = $distributorRow['uses_live_price'];
								break;
							}
						}
					}

					$instaClickEnabled = false;
					if (count($dealerArray['distributorsAutoOrder']) > 0 && in_array($row['distributor_id'],$dealerArray['distributorsAutoOrder'])) {
						$instaClickEnabled = true;
					}
					// allocated stock is only available to insta-click enabled dealers
					// and is limited to 1 per customer
					// $quantityForAutoOrder is used in the admin apps for assembly orders
					if ($row['allocated'] > 0) {
						$allocated += $row['allocated'];
						$quantity += $row['quantity'] - $row['allocated'] + ($instaClickEnabled ? 1 : 0);
						$quantityForAutoOrder += $row['quantity'] - $row['allocated'];
					} else {
						$quantity += $row['quantity'];
						$quantityForAutoOrder += ($instaClickEnabled ? $row['quantity'] : 0);
					}

					$dealerCost += $row['dealer_cost'];
					$distributorArray[] = $row['distributor_id'];
				}
				$productArray['total_quantity'] = $quantity;
				$productArray['total_allocated'] = $allocated;
				$productArray['average_dealer_cost'] = (count($distributorArray)>0 ? round($dealerCost / count($distributorArray),2) : 0);
				$productArray['distributors'] = $distributorArray;
				
				$resultSet = executeQuery("select sum(quantity) as quantity from order_items left join orders using (order_id) where product_id = ? and order_date = ?",$productId,date('Y-m-d'));
				$row = getNextRow($resultSet);
				$productArray['pending_orders'] = $row['quantity'];

				$productArray['available_dealer_quantity'] = $productArray['total_quantity'] - $productArray['total_allocated'] - $productArray['pending_orders'];
				$productArray['available_quantity'] = $productArray['available_dealer_quantity']  + ($productArray['total_allocated'] > 0 ? 1 : 0);

				$productArray['available_quantity'] = $productArray['total_quantity'] - $productArray['pending_orders'];
				$productArray['available_for_auto_order'] = $quantityForAutoOrder;
			}
			if ($productArray['available_quantity'] > 0 && $productArray['available_quantity'] < 6) {
				$productArray['low_quantity'] = true; // used for alerts on catalog and checkout pages
			}
		}
	}
	return $productArray;
}

################
#getShippingGroup - set the shipping group for a given product
################
function getShippingGroup($productId) {
	$categoryId = getFieldFromId('category_id','products','product_id',$productId);
        $isFirearm = getFieldFromId('is_firearm','products','product_id',$productId);
	$productTypeId = getFieldFromId('product_type_id','categories','category_id',$categoryId);
	$shippingGroup = getFieldFromId('product_type_code','product_types','product_type_id',$productTypeId);
	if (empty($shippingGroup)) {
		$departmentId = getFieldFromId('department_id','categories','category_id',$categoryId);
		$productTypeId = getFieldFromId('product_type_id','departments','department_id',$departmentId);
		$shippingGroup = getFieldFromId('product_type_code','product_types','product_type_id',$productTypeId);
		if (empty($shippingGroup)) {
			// fallback options
			switch($departmentId) {
				case 1:
				case 3:
					$shippingGroup = "firearms";
					break;
				case 2:
					$shippingGroup = "handguns";
					break;
				case 4:
					$shippingGroup = "nfa";
					break;
				case 9:
					$shippingGroup = "ammo";
					break;
				default:
					$shippingGroup = "items";
					break;
			}
		}
	}
        if (strtolower($shippingGroup) == "items" || strtolower($shippingGroup) == "ammo") {
            if($isFirearm){
                $shippingGroup = "firearms";
            }
        }
	return strtolower($shippingGroup);
}

################
#getImageHashCode - save an image to the file system if necessary
################
function getImageHashCode($imageId,$overwrite=false) {
     
        $resultSet = executeQuery("select image_id,hash_code,file_type,version from images where image_id = ?",$imageId);

    
	if ($row = getNextRow($resultSet)) {
		$hashCode = $row['hash_code'];
		if (empty($hashCode)) {
			$hashCode = getRandomString();
			executeQuery("update images set hash_code = ? where image_id = ?",$hashCode,$imageId);
		}
		$filename = $_SERVER['DOCUMENT_ROOT'] . "/imagedb/image" . $imageId . "-" . $hashCode . ".jpg";
		if (!file_exists($filename) || $overwrite) {
		$resultSet = executeQuery("select file_content from images where image_id = ?",$imageId);
  
                if ($row = getNextRow($resultSet))
                {
  
                        $handle = fopen($filename, 'w');
			fwrite($handle,$row['file_content']);
			fclose($handle);
                }
     
		}
	}
	return $hashCode;
}

################
#getImageHashCodeB - save an image to the file system if necessary -- honor file_type
################
function getImageHashCodeB($imageId,$overwrite=false) {

        $resultSet = executeQuery("select image_id,hash_code,file_type,version from images where image_id = ?",$imageId);
        

	if ($row = getNextRow($resultSet)) {
		$hashCode = $row['hash_code'];
		if (empty($hashCode)) {
			$hashCode = getRandomString();
			executeQuery("update images set hash_code = ? where image_id = ?",$hashCode,$imageId);
		}
		switch($row['file_type']) {
			case 1:
				$ext = ".gif";
				break;
			case 3:
				$ext = ".png";
				break;
			case 2:
			default:
				$ext = ".jpg";
				break;
		}
		$filename = $_SERVER['DOCUMENT_ROOT'] . "/imagedb/image" . $imageId . "-" . $hashCode . $ext;
		if (!file_exists($filename) || $overwrite) {
		$resultSet = executeQuery("select file_content from images where image_id = ?",$imageId);  	
                if ($row = getNextRow($resultSet))
                {
                        $handle = fopen($filename, 'w');
			fwrite($handle,$row['file_content']);
			fclose($handle);
                }
		}
	}
	return $hashCode . $ext;
}

#################
#canRunProgramCode - given a program code, return the permission level the user has to this program
#################
function canRunProgramCode($programCode) {
	global $gAdministratorFlag;

	$permissionLevels = array("none"=>0,"readonly"=>1,"read/write"=>2,"full"=>3);

	if ($gAdministratorFlag) {
		return $permissionLevels['full'];
	}
	$resultSet = executeQuery("select program_id from programs where program_code = ?",strtoupper($programCode));
	if ($row = getNextRow($resultSet)) {
		return canRunProgram($row['program_id']);
	}
	return 0;
}

#################
#canRunProgram - given a program id, return the permission level the has
#################
function canRunProgram($programId) {
	global $gLoggedIn;
	global $gAdministratorFlag;
	global $gUserId;
	global $gUserTypeId;

	$permissionLevels = array("none"=>0,"readonly"=>1,"read/write"=>2,"full"=>3);

	if ($gAdministratorFlag) {
		return $permissionLevels['full'];
	}
	if (empty($programId)) {
		return $permissionLevels['none'];
	}
	$resultSet = executeQuery("select * from programs where program_id = ?",$programId);
	if (!$programRow = getNextRow($resultSet)) {
		return $permissionLevels['none'];
	}

	$publicAccess = $programRow['public_access'] == 1;
	$allUserAccess = $programRow['all_user_access'] == 1;

# If it is a public access program, then of course they can use it.

	if ($publicAccess) {
		return $permissionLevels['full'];
	}

# Check to see if the user can use the program

	$userPermissionLevel = ($gLoggedIn && $allUserAccess ? $permissionLevels['full'] : $permissionLevels['none']);
	if (!empty($gUserTypeId)) {
		$resultSet = executeQuery("select permission_level from user_type_access where " .
			"user_type_id = ? and program_id = ? order by permission_level desc",$gUserTypeId,$programId);
		if ($row = getNextRow($resultSet)) {
			$userPermissionLevel = $row['permission_level'];
		}
	}

# Check permission allowed for the user, since this can also override the universal access and the user type access.

	if (!empty($gUserId)) {
		$resultSet = executeQuery("select permission_level from user_access where " .
			"user_id = ? and program_id = ? order by permission_level desc",$gUserId,$programId);
		if ($row = getNextRow($resultSet)) {
			$userPermissionLevel = $row['permission_level'];
		}
	}

	return $userPermissionLevel;
}
# For Greatest Common Divisor 

function GCD ($a, $b)
{
    while ( $b != 0)
    {
    $remainder = $a % $b;
    $a = $b;
    $b = $remainder;
    }
    return abs ($a);
}
# Getting Client IP Addresss
function get_client_ip() {
     $ipaddress = '';
     if ($_SERVER['HTTP_CLIENT_IP'])
         $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
     else if($_SERVER['HTTP_X_FORWARDED_FOR'])
         $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
     else if($_SERVER['HTTP_X_FORWARDED'])
         $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
     else if($_SERVER['HTTP_FORWARDED_FOR'])
         $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
     else if($_SERVER['HTTP_FORWARDED'])
         $ipaddress = $_SERVER['HTTP_FORWARDED'];
     else if($_SERVER['REMOTE_ADDR'])
         $ipaddress = $_SERVER['REMOTE_ADDR'];
     else
         $ipaddress = 'UNKNOWN';

     return $ipaddress; 
}
# Inventory Check for the Distributor - Sports South

function priorInventoryCheck($dealerId,$productCode,$quantityOrdered)
{
$resultSet = executeQuery("select * from dealer_distributors where dealer_id = ? and distributor_id = ? and inactive = 0", $dealerId, 6);
    	if ($row = getNextRow($resultSet)) {
		if ($dropShipEnabled == 1) {
		    $accountNumber = $row['ic_account_number_alt'];
		    $accountPassword = $row['ic_password_alt'];
		} else {
		    $accountNumber = $row['ic_account_number'];
		    $accountPassword = $row['ic_password'];
		}

	$credentials = "CustomerNumber=" . $accountNumber;
	$credentials .= "&UserName=" . $accountNumber;
	$credentials .= "&Password=" . $accountPassword;
	$credentials .= "&Source=NFDN";
	$params = "&ItemNumber=" . $productCode;//$productRow['product_code'];
                $url = "http://webservices.theshootingwarehouse.com/smart/inventory.asmx/OnhandInquiry";
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $credentials . $params);
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                $data = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($httpCode == 200) {
                    $xml = simplexml_load_string(html_entity_decode($data));
                    $price = $xml->NewDataSet[0]->Table[0]->C;
                    $quantityInStock = $xml->NewDataSet[0]->Table[0]->Q;
                }

                $dealerCost = (float) $price; // multiplier converts to true number
                		if ($dealerCost > 0 && (float) $quantityInStock > 0 && $quantityInStock >= $quantityOrdered) {
					return 1;
                                        
				}
				else
				{
					return 6;
                                        
				}
}
}

#################
#Exception block list
#################
function exceptionBlockList($exceptionID)
{
$expProducts[] = array();
if (!empty($exceptionID)) {	
 	$expMemcacheKey = "Exception_Products_" . $exceptionID;   	
 	if (class_exists(Memcache) && $memcache = new Memcache()) {
            foreach ($gMemcacheServers as $server) {
                $memcache->addServer($server);
            }            
            if($cacheDataStr = $memcache->get($expMemcacheKey)) 
	    {		
	          $expProducts = json_decode($cacheDataStr, true);                  
	    }	 
	    else{
       	        $expQuery = "select product_id from exception_list_products where exception_list_id = " . $exceptionID;
		$resultSet = executeQuery($expQuery);
		while ($row = getNextRow($resultSet)) 
		  {
			   $expProducts[] = $row['product_id'];
		  }
		$memcache->set($expMemcacheKey, json_encode($expProducts), false, 43200);  
	    }       
	}
}
return $expProducts;

} 
#################
#token set 
#################
function setToken($token, $sess_id)
{

$cQuery = "select * from shopping_cart_token where token = ?";
$resultSet =  executeQuery($cQuery, $token);
if($resultSet['affected_rows'] == 0){
$query = "insert into shopping_cart_token (token, session_id) values (?,?)";
executeQuery($query, $token, $sess_id);
}
}
#################
#check token
#################
function checkToken($token, $sess_id)
{
$flag = false;
$cQuery = "select * from shopping_cart_token where token = ? and session_id = ?	 ";
$resultSet =  executeQuery($cQuery, $token, $sess_id);
if($resultSet['affected_rows'] != 0){
$flag = true;
}
return $flag;
}



function accessLogTrace($message)
{
   $fp = fopen('/var/www/logs/accessLog/accessLogProd2#'.date('Y-m-d').'#'.date('H').'.txt', 'a');
   fwrite($fp, date('Y-m-d h:i:s a',time()).' '.$message."\r\n"); 
   fclose($fp);
} 
