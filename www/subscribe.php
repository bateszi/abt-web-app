<?php
require_once '../vendor/autoload.php';
require_once '_lib.php';

$response = ['success' => false];

header('Content-type: application/json; charset=utf-8');

if (isLoggedIn()) {

	$payload = file_get_contents('php://input');
	$decoded = @json_decode($payload, true);

	if ($decoded) {
		$db = getDbConnection();
		$siteId = $decoded['siteId'] ?? -1;
		$type = $decoded['type'] ?? 'a';

		if ($siteId > -1) {
			if ($type === 'd') {
				$query = "DELETE FROM `users_subscriptions` WHERE fk_user_id = ? AND fk_site_id = ?";
			} else {
				$query = "INSERT INTO `users_subscriptions` (`fk_user_id`, `fk_site_id`) VALUES (?, ?)";
			}

			$prepare = $db->prepare($query);
			$response['success'] = $prepare->execute([$_SESSION['user_id'], $siteId]);
		}
	}

}

echo json_encode($response);
