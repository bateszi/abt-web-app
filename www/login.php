<?php
require_once '../vendor/autoload.php';
require_once '_lib.php';

$db = getDbConnection();
$config = getConfig();
$client = getGoogleOAuthClient($config['oauthRedirectUri']);
$isLoggedIn = isLoggedIn();

$authUrl = '';
$email = '';

if (!$isLoggedIn) {
	$authUrl = $client->createAuthUrl();
} else {
	$email = $_SESSION['email'] ?? '';
}

if (isset($_GET['code'])) {
	$authCode = $_GET['code'];
	$token = $client->fetchAccessTokenWithAuthCode($authCode);
	$_SESSION['id_token_token'] = $token;

	$tokenData = getGoogleIdTokenData($client);

	if (!empty($tokenData)) {
		$email = (string)$tokenData["email"];

		$userId = findUser($db, $email);

		if ($userId === -1) {
			if (createUser($db, $email)) {
				$userId = findUser($db, $email);
			}
		}

		if ($userId > -1) {
			$_SESSION['user_id'] = $userId;
			$_SESSION['email'] = $email;

			updateUserLoginTime($db, $userId, date('Y-m-d H:i:s'));
		}
	}

	header('Location: ' . filter_var($config['oauthRedirectUri'], FILTER_SANITIZE_URL));
	return;
}

$loader = new \Twig\Loader\FilesystemLoader('../templates');

$twig = new \Twig\Environment($loader, [
	'cache' => '../templates_cache',
	'debug' => true,
	'strict_variables' => true,
]);

echo $twig->render('login.twig', [
	'isLoggedIn' => $isLoggedIn,
	'authUrl' => $authUrl,
	'email' => $email,
]);
