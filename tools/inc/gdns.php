<?php
/* tools/inc/gdns.php
 * Functions for interacting with Google's DNS ove TCP service.
 *
 * In general, gdns functions return an array containing:
 *    idx 0: boolean indicating success or failure
 *    idx 1: an array or string of the desired response, depending on the function invoked
 */

function gdnsExecute($domain, $qtype) {
	$ch = curl_init("https://dns.google.com/resolve?name=" . $domain . "&type=" . $qtype);
	
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
	
	$response = curl_exec($ch);
	$err = curl_error($ch);
	
	if($err) {
		//trigger_error("Detected CURL error when querying GDNS", E_USER_WARNING);
		return [false, [$err, false]]; // so array=true
	} else {
		$json = json_decode($response, true);
		
		if(!$json) {
			//trigger_error("Detected JSON response invalid from GDNS", E_USER_WARNING);
			return [false, $response];
		}
		
		if($json["Status"] != 0) {
			//trigger_error("Detected DNS error response code from GDNS - got " . $json["Status"], E_USER_WARNING);
			return [false, $json];
		}
		
		return [true, json_decode($response, true)];
	}
}

function gdnsGetGeneralLooped($domain, $qtype) {
	$ret = gdnsExecute($domain, $qtype);
	$retArr = [];
	
	if($ret[0]) {
		if(array_key_exists("Answer", $ret[1])) {
			foreach($ret[1]["Answer"] as $retAns) {
				$retArr[] = $retAns["data"];
			}
			return [true, $retArr];
		} else {
			return [false, $ret[1]];
		}
	} else {
		return $ret;
	}
}

function gdnsGetGeneral($domain, $qtype) {
	$finished = false;
	$max = 3;
	$i = 0;
	
	while(!$finished && $i < $max) {
		$ret = gdnsGetGeneralLooped($domain, $qtype);
		if($ret[0]) {
			$finished = true;
		} if(!$ret[0] && !is_array($ret[1])) {
			$finished = true;
		}
		$i++;
		sleep(1);
	}
	
	sleep(1); // temporary ratelimit avoidance measure
	return $ret;
}

?>
