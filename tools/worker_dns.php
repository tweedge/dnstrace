<?php
/* tools/worker_dns.php
 * Update single FQDN using Google resolvers
 */

include "inc/setup.php";
include "inc/gdns.php";

if(count($argv) != 2) {
	echo "dnstrace - worker_dns.php" . PHP_EOL;
	echo "  This shouldn't be invoked manually unless it's for debugging." . PHP_EOL;
	echo PHP_EOL;
	echo "Usage: php worker_dns.php \"FQDN\"" . PHP_EOL;
	echo "  Given FQDN will be searched for using Google resolvers & its data saved." . PHP_EOL;
	include "inc/exit.php";
}

use LayerShifter\TLDExtract\Extract;
$ext = new Extract(null, null, Extract::MODE_ALLOW_ICANN);
$parsedDomain = $ext->parse($argv[1]);

$gdnsResA = gdnsGetGeneral($parsedDomain->getFullHost(), "A");
if($gdnsResA[0]) {
	foreach($gdnsResA[1] as $rawResult) {
		if(filter_var($rawResult, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
			$mysqli->query("INSERT INTO `DNS_A` (Subdomain, Domain, IPv4, Current) VALUES ('".$parsedDomain["subdomain"]."', '".$parsedDomain->getRegistrableDomain()."', '".$rawResult."', 1)");
			echo "   " . $rawResult . PHP_EOL;
		}
	}
} else {
	echo "   No A records" . PHP_EOL;
}

$gdnsResAAAA = gdnsGetGeneral($parsedDomain->getFullHost(), "AAAA");
if($gdnsResAAAA[0]) {
	foreach($gdnsResAAAA[1] as $rawResult) {
		if(filter_var($rawResult, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
			$mysqli->query("INSERT INTO `DNS_AAAA` (Subdomain, Domain, IPv6, Current) VALUES ('".$parsedDomain["subdomain"]."', '".$parsedDomain->getRegistrableDomain()."', '".$rawResult."', 1)");
			echo "   " . $rawResult . PHP_EOL;
		}
	}
} else {
	echo "   No AAAA records" . PHP_EOL;
}

$gdnsResCNAME = gdnsGetGeneral($parsedDomain->getFullHost(), "CNAME");
if($gdnsResCNAME[0]) {
	foreach($gdnsResCNAME[1] as $rawResult) {
		$parsedRes = $ext->parse(rtrim($rawResult, "."));
		if($parsedRes->isValidDomain()) {
			$mysqli->query("INSERT INTO `DNS_CNAME` (Subdomain, Domain, CNAME, Current) VALUES ('".$parsedDomain["subdomain"]."', '".$parsedDomain->getRegistrableDomain()."', '".$parsedRes->getFullHost()."', 1)");
			echo "   " . $parsedRes->getFullHost() . PHP_EOL;
		}
	}
} else {
	echo "   No CNAME records" . PHP_EOL;
}

$gdnsResMX = gdnsGetGeneral($parsedDomain->getFullHost(), "MX");
if($gdnsResMX[0]) {
	foreach($gdnsResMX[1] as $rawResult) {
		$arrRes = explode(" ", $rawResult);
		if(count($arrRes) == 1) {
			$parsedRes = $ext->parse(rtrim($arrRes[0], "."));
		} else {
			$parsedRes = $ext->parse(rtrim($arrRes[1], "."));
		}
		$ans = $parsedRes->getFullHost();
		$mysqli->query("INSERT INTO `DNS_MX` (Subdomain, Domain, MX_Subdomain, MX_Domain, Current) VALUES ('".$parsedDomain["subdomain"]."', '".$parsedDomain->getRegistrableDomain()."', '".$parsedRes["subdomain"]."', '".$parsedRes->getRegistrableDomain()."', 1)");
		echo "   " . $ans . PHP_EOL;
	}
} else {
	echo "   No MX records" . PHP_EOL;
}

$gdnsResNS = gdnsGetGeneral($parsedDomain->getFullHost(), "NS");
if($gdnsResNS[0]) {
	foreach($gdnsResNS[1] as $rawResult) {
		$ext = new Extract(null, null, Extract::MODE_ALLOW_ICANN);
		$parsedRes = $ext->parse(rtrim($rawResult, "."));
		if($parsedRes->isValidDomain()) {
			$ans = $parsedRes->getFullHost();
			$mysqli->query("INSERT INTO `DNS_NS` (Subdomain, Domain, NS_Subdomain, NS_Domain, Current) VALUES ('".$parsedDomain["subdomain"]."', '".$parsedDomain->getRegistrableDomain()."', '".$parsedRes["subdomain"]."', '".$parsedRes->getRegistrableDomain()."', 1)");
			echo "   " . $ans . PHP_EOL;
		}
	}
} else {
	echo "   No NS records" . PHP_EOL;
}

if(strcmp($parsedDomain->getFullHost(), $parsedDomain->getRegistrableDomain()) === 0) {
	// WHOIS PENDING
	//$pythonWhois = json_decode(exec($configDepWhois . " -j " . $parsedDomain->getFullHost()), true);
	//var_dump($pythonWhois);
} else {
	echo "   Not eligible for WHOIS" . PHP_EOL;
}

$mysqli->query("UPDATE `Worker_DNS` SET Count = Count - 1");

include "inc/exit.php";
?>