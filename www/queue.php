<?php
require_once '../vendor/autoload.php';
require_once '_lib.php';

function addFeed(PDO $db, string $url) {
	$prepare = $db->prepare("SELECT pk_site_id FROM sites WHERE feed_url = ?");
	$prepare->execute([$url]);
	$sites = $prepare->rowCount();

	if ($sites === 0) {
		$prepareNewFeed = $db->prepare("INSERT INTO `sites` (`feed_url`) VALUES (?)");
		$prepareNewFeed->execute([$url]);
	}
}

function changeSiteStatus(PDO $db, int $id, string $status) {
	$prepareStatus = $db->prepare("UPDATE `discovered_sites_queue` SET `status` = ? WHERE (`pk_prospect_id` = ?)");
	$prepareStatus->execute([$status, $id]);
}

function addSiteToBlacklist(PDO $db, int $id, string $fqdn) {
	$prepareBl = $db->prepare("INSERT INTO `discovered_sites_blacklist` (`host`) VALUES (?)");
	$prepareBl->execute([$fqdn]);

	changeSiteStatus($db, $id, 'rejected');
}

function getSites(PDO $db, $dateFilter = '', $status = 'pending', $order = 'overall DESC, score DESC, encountered DESC', $offset = 0, $limit = 100): array {
	$dateCondition = '';

	if ($dateFilter === '7days') {
		$dateCondition = sprintf('AND created >= "%s"', date('Y-m-d H:i:s', strtotime('-1 week')));
	}

	$query = sprintf("SELECT pk_prospect_id, fqdn, feed_url, created, status, score, encountered, (score * encountered) as overall
		FROM discovered_sites_queue
		WHERE status = ?
		%s
		ORDER BY %s
		LIMIT %d OFFSET %d", $dateCondition, $order, $limit, $offset);
	$prepare = $db->prepare($query);
	$prepare->execute([$status]);

	return $prepare->fetchAll(PDO::FETCH_ASSOC);
}

$db = getDbConnection();

if (isset($_POST['site_id'])) {
	$siteId = $_POST['site_id'] ?? -1;
	$fqdn = $_POST['fqdn'] ?? '';
	$feedUrl = $_POST['feed_url'] ?? '';
	$status = $_POST['status'] ?? 'pending';

	switch ($status) {
		case "accept":
			$feedUrl = trim($feedUrl);

			if (!empty($feedUrl)) {
				addFeed($db, $feedUrl);
			}

			changeSiteStatus($db, $siteId, 'accepted');
			break;

		case "reject":
			changeSiteStatus($db, $siteId, 'rejected');
			break;

		case "blacklist":
			addSiteToBlacklist($db, $siteId, $fqdn);
			break;

		default:
			break;
	}

	header('Location: ' . $_SERVER["REQUEST_URI"]);
}

if ($db !== false) {
	$dateFilter = $_GET['added'] ?? '';

	$sites = getSites($db, $dateFilter);

	$twig = initTwig();

	echo $twig->render('queue.twig', [
		'sites' => $sites
	]);
}
