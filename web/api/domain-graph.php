<?php
/* web/api/domain-graph.php
 * Force directed graph by domain only.
 */

include "inc/setup.php";
use LayerShifter\TLDExtract\Extract;

$ext = new Extract(null, null, Extract::MODE_ALLOW_ICANN);

function fixDomain($subdomain, $domain) {
	if(strlen($subdomain) > 0) {
		return $subdomain . "." . $domain;
	} else {
		return $domain;
	}
}

function updateLink($links, $plink) {
	$linkExists = false;
	foreach($links as $link) {
		if($link["source"] == $plink["source"] && $link["target"] == $plink["target"]) {
			$linkExists = true;
			break;
		}
	}
	if(!$linkExists) {
		$links[] = array(
			"source" => $plink["source"],
			"target" => $plink["target"]
		);
	}
	return $links;
}

$preNodes = [];
$nodes = [];
$links = [];
$performed = [];

function perform($preNodes, $links, $mysqli, $ext, $performed, $MXEN, $NSEN, $jobID) {
	foreach($preNodes as $FQDN) {
		$lookupFQDN = $ext->parse($FQDN);
		
		if(!$lookupFQDN->isValidDomain()) {
			continue;
		}

		$dbGet = $mysqli->query("SELECT * FROM `Reputation` WHERE `Domain` = '" . $lookupFQDN->getRegistrableDomain() . "'");

		if(mysqli_num_rows($dbGet) == 0) {
			continue;
		}

		if(!in_array($lookupFQDN->getRegistrableDomain(), $preNodes)) {
			$preNodes[] = $lookupFQDN->getRegistrableDomain();
		}

		$srcID = array_search($lookupFQDN->getRegistrableDomain(), $preNodes);
		while($row = $dbGet->fetch_assoc()) {
			if(!in_array(fixDomain($row["Subdomain"], $row["Domain"]), $preNodes)) {
				$preNodes[] = fixDomain($row["Subdomain"], $row["Domain"]);
			}
		}
		
		if(in_array($lookupFQDN->getRegistrableDomain(), $performed)) {
			continue;
		}
		
		$mysqli->query('UPDATE `Jobs` SET `Current` = "PROCESSING ' . $lookupFQDN->getRegistrableDomain() . '" WHERE `JobID` = ' . $jobID);
		$performed[] = $lookupFQDN->getRegistrableDomain();

		// A RECORD LOOKUP SECTION
		$dbGet = $mysqli->query("SELECT * FROM `DNS_A` WHERE `Domain` = '" . $lookupFQDN->getRegistrableDomain() . "'");

		$reducer = [];
		while($row = $dbGet->fetch_assoc()) {
			if(!in_array($row["IPv4"], $preNodes)) {
				$preNodes[] = $row["IPv4"];
			}
			$link = array(
				"source" => array_search(fixDomain($row["Subdomain"], $row["Domain"]), $preNodes),
				"target" => array_search($row["IPv4"], $preNodes)
			);
			$links = updateLink($links, $link);
			$reducer[] = $row["IPv4"];
		}
		$reducer = array_unique($reducer);

		foreach($reducer as $IPv4Addr) {
			$srcID = array_search($IPv4Addr, $preNodes);
			$dbGet = $mysqli->query("SELECT * FROM `DNS_A` WHERE `IPv4` = '" . $IPv4Addr . "' AND `Domain` != '" . $lookupFQDN->getRegistrableDomain() . "'");
			
			while($row = $dbGet->fetch_assoc()) {
				if(!in_array(fixDomain($row["Subdomain"], $row["Domain"]), $preNodes)) {
					$preNodes[] = fixDomain($row["Subdomain"], $row["Domain"]);
				}
				$link = array(
					"source" => $srcID,
					"target" => array_search(fixDomain($row["Subdomain"], $row["Domain"]), $preNodes)
				);
				$links = updateLink($links, $link);
			}
		}
		// A RECORD LOOKUP SECTION

		// AAAA RECORD LOOKUP SECTION
		$dbGet = $mysqli->query("SELECT * FROM `DNS_AAAA` WHERE `Domain` = '" . $lookupFQDN->getRegistrableDomain() . "'");

		$reducer = [];
		while($row = $dbGet->fetch_assoc()) {
			if(!in_array($row["IPv6"], $preNodes)) {
				$preNodes[] = $row["IPv6"];
			}
			$link = array(
				"source" => array_search(fixDomain($row["Subdomain"], $row["Domain"]), $preNodes),
				"target" => array_search($row["IPv6"], $preNodes)
			);
			$links = updateLink($links, $link);
			$reducer[] = $row["IPv6"];
		}
		$reducer = array_unique($reducer);

		foreach($reducer as $IPv6Addr) {
			$srcID = array_search($IPv6Addr, $preNodes);
			$dbGet = $mysqli->query("SELECT * FROM `DNS_AAAA` WHERE `IPv6` = '" . $IPv6Addr . "' AND `Domain` != '" . $lookupFQDN->getRegistrableDomain() . "'");
			
			while($row = $dbGet->fetch_assoc()) {
				if(!in_array(fixDomain($row["Subdomain"], $row["Domain"]), $preNodes)) {
					$preNodes[] = fixDomain($row["Subdomain"], $row["Domain"]);
				}
				$link = array(
					"source" => $srcID,
					"target" => array_search(fixDomain($row["Subdomain"], $row["Domain"]), $preNodes)
				);
				$links = updateLink($links, $link);
			}
		}
		// AAAA RECORD LOOKUP SECTION

		// CNAME RECORD LOOKUP SECTION
		$dbGet = $mysqli->query("SELECT * FROM `DNS_CNAME` WHERE `Domain` = '" . $lookupFQDN->getRegistrableDomain() . "'");

		$reducer = [];
		while($row = $dbGet->fetch_assoc()) {
			if(!in_array($row["CNAME"], $preNodes)) {
				$preNodes[] = $row["CNAME"];
			}
			$link = array(
				"source" => array_search(fixDomain($row["Subdomain"], $row["Domain"]), $preNodes),
				"target" => array_search($row["CNAME"], $preNodes)
			);
			$links = updateLink($links, $link);
			$reducer[] = $row["CNAME"];
		}
		$reducer = array_unique($reducer);

		foreach($reducer as $CNAME) {
			$srcID = array_search($CNAME, $preNodes);
			$dbGet = $mysqli->query("SELECT * FROM `DNS_CNAME` WHERE `CNAME` = '" . $CNAME . "' AND `Domain` != '" . $lookupFQDN->getRegistrableDomain() . "'");
			
			while($row = $dbGet->fetch_assoc()) {
				if(!in_array(fixDomain($row["Subdomain"], $row["Domain"]), $preNodes)) {
					$preNodes[] = fixDomain($row["Subdomain"], $row["Domain"]);
				}
				$link = array(
					"source" => $srcID,
					"target" => array_search(fixDomain($row["Subdomain"], $row["Domain"]), $preNodes)
				);
				$links = updateLink($links, $link);
			}
		}
		// CNAME RECORD LOOKUP SECTION

		// MX RECORD LOOKUP SECTION
		if($MXEN) {
			$dbGet = $mysqli->query("SELECT * FROM `DNS_MX` WHERE `Domain` = '" . $lookupFQDN->getRegistrableDomain() . "'");

			$reducer = [];
			while($row = $dbGet->fetch_assoc()) {
				if(!in_array(fixDomain($row["MX_Subdomain"], $row["MX_Domain"]), $preNodes)) {
					$preNodes[] = fixDomain($row["MX_Subdomain"], $row["MX_Domain"]);
				}
				$link = array(
					"source" => array_search(fixDomain($row["Subdomain"], $row["Domain"]), $preNodes),
					"target" => array_search(fixDomain($row["MX_Subdomain"], $row["MX_Domain"]), $preNodes)
				);
				$links = updateLink($links, $link);
				$reducer[] = $row["MX_Subdomain"] . ":" . $row["MX_Domain"];
			}
			$reducer = array_unique($reducer);

			foreach($reducer as $MXD) {
				$temp = explode(":", $MXD);
				$srcID = array_search(fixDomain($temp[0], $temp[1]), $preNodes);
				$dbGet = $mysqli->query("SELECT * FROM `DNS_MX` WHERE CONCAT(`MX_Subdomain`, ':', `MX_Domain`) = '" . $MXD . "' AND `Domain` != '" . $lookupFQDN->getRegistrableDomain() . "'");
				
				while($row = $dbGet->fetch_assoc()) {
					if(!in_array(fixDomain($row["Subdomain"], $row["Domain"]), $preNodes)) {
						$preNodes[] = fixDomain($row["Subdomain"], $row["Domain"]);
					}
					$link = array(
						"source" => $srcID,
						"target" => array_search(fixDomain($row["Subdomain"], $row["Domain"]), $preNodes)
					);
					$links = updateLink($links, $link);
				}
			}
		}
		// MX RECORD LOOKUP SECTION

		// NS RECORD LOOKUP SECTION
		if($NSEN) {
			$dbGet = $mysqli->query("SELECT * FROM `DNS_NS` WHERE `Domain` = '" . $lookupFQDN->getRegistrableDomain() . "'");

			$reducer = [];
			while($row = $dbGet->fetch_assoc()) {
				if(!in_array(fixDomain($row["NS_Subdomain"], $row["NS_Domain"]), $preNodes)) {
					$preNodes[] = fixDomain($row["NS_Subdomain"], $row["NS_Domain"]);
				}
				$link = array(
					"source" => array_search(fixDomain($row["Subdomain"], $row["Domain"]), $preNodes),
					"target" => array_search(fixDomain($row["NS_Subdomain"], $row["NS_Domain"]), $preNodes)
				);
				$links = updateLink($links, $link);
				$reducer[] = $row["NS_Subdomain"] . ":" . $row["NS_Domain"];
			}
			$reducer = array_unique($reducer);

			foreach($reducer as $NSD) {
				$temp = explode(":", $NSD);
				$srcID = array_search(fixDomain($temp[0], $temp[1]), $preNodes);
				$dbGet = $mysqli->query("SELECT * FROM `DNS_NS` WHERE CONCAT(`NS_Subdomain`, ':', `NS_Domain`) = '" . $NSD . "' AND `Domain` != '" . $lookupFQDN->getRegistrableDomain() . "'");
				
				while($row = $dbGet->fetch_assoc()) {
					if(!in_array(fixDomain($row["Subdomain"], $row["Domain"]), $preNodes)) {
						$preNodes[] = fixDomain($row["Subdomain"], $row["Domain"]);
					}
					$link = array(
						"source" => $srcID,
						"target" => array_search(fixDomain($row["Subdomain"], $row["Domain"]), $preNodes)
					);
					$links = updateLink($links, $link);
				}
			}
		}
		// NS RECORD LOOKUP SECTION
	}
	
	return array($preNodes, $links, $performed);
}

if(count($argv) != 2) {
	echo "Incorrect args provided.";
	include "inc/exit.php";
}
$jobID = $argv[1];
$dbGet = $mysqli->query("SELECT * FROM `Jobs` WHERE `JobID` = " . $jobID);

if(mysqli_num_rows($dbGet) != 1) {
	echo "Couldn't find job, or found too many.";
	include "inc/exit.php";
}
$jobData = $dbGet->fetch_array();
if($jobData["MXEN"] == 1) {
	$MXEN = true;
} else {
	$MXEN = false;
}
if($jobData["NSEN"] == 1) {
	$NSEN = true;
} else {
	$NSEN = false;
}

$base = $ext->parse($jobData["Domain"]);
$preNodes[] = $base->getRegistrableDomain();

for($i = 0; $i < $jobData["Degree"]; $i++) {
	list($preNodes, $links, $performed) = perform($preNodes, $links, $mysqli, $ext, $performed, $MXEN, $NSEN, $jobID);
}

// cleanup
foreach($preNodes as $node) { // placeholder
	$nodeObj = $ext->parse($node);
	if(strcmp($nodeObj->getRegistrableDomain(), $node) !== 0) {
		if(in_array($nodeObj->getRegistrableDomain(), $preNodes)) {
			$link = array(
				"source" => array_search($nodeObj->getRegistrableDomain(), $preNodes),
				"target" => array_search($node, $preNodes)
			);
			$links = updateLink($links, $link);
		}
	}
	$nodes[] = array(
		"id" => $node,
		"type" => "circle"
	);
}

$mysqli->query('UPDATE `Jobs` SET `Current` = "DONE" WHERE `JobID` = ' . $jobID);
$mysqli->query("UPDATE `Processors` SET Count = Count - 1");

$buildReturnable = array("graph" => array(), "links" => $links, "nodes" => $nodes);

echo json_encode($buildReturnable);
?>