<?php
/* tools/update_all_dns.php
 * Execute a full domain database update.
 */

include "inc/setup.php";

if(count($argv) != 2) {
	echo "dnstrace - update_all_dns.php" . PHP_EOL;
	echo "  Fetches data for all domains." . PHP_EOL;
	echo "  Ideally, run this automatically off-use-hours for minimal business impact." . PHP_EOL;
	echo PHP_EOL;
	echo "Usage: php update_all_dns.php [# of workers]" . PHP_EOL;
	echo "  For maximum performance, the # of workers should be ~8x the # of cores available." . PHP_EOL;
	include "inc/exit.php";
}

$maxWkr = intval($argv[1]);
$mysqli->query("TRUNCATE `Worker_DNS`");
$mysqli->query("INSERT INTO `Worker_DNS` (Count) VALUES(0)");

$allDomains = $mysqli->query("SELECT * FROM `Reputation`");
while($row = $allDomains->fetch_assoc()) {
	$stay = true;
	while($stay) {
		$dbWorker = $mysqli->query("SELECT * FROM `Worker_DNS`");
		$currentCtr = $dbWorker->fetch_assoc()["Count"];
		
		if($maxWkr > $currentCtr) {
			if(strlen($row["Subdomain"]) > 0) {
				exec("php worker_dns.php \"". $row["Subdomain"] . "." . $row["Domain"] . "\" > /dev/null &");
				echo "assigned \"". $row["Subdomain"] . "." . $row["Domain"] . "\" ";
			} else {
				exec("php worker_dns.php \"" . $row["Domain"] . "\" > /dev/null &");
				echo "assigned \"" . $row["Domain"] . "\" ";
			}
			
			echo "(".($currentCtr+1)."/".$maxWkr." workers active)" . PHP_EOL;
			
			$mysqli->query("UPDATE `Worker_DNS` SET Count = Count + 1");
			$stay = false;
		} else {
			usleep(50000);
		}
	}
}

include "inc/exit.php";
?>