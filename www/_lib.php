<?php
session_start();

function getConfig(): array {
	$pathToConfig = __DIR__ . '/../app-config/config.php';
	return require $pathToConfig;
}

function getDbConnection() {
	$config = getConfig();

	try
	{
		return new PDO(
			sprintf('mysql:host=%s;dbname=%s;charset=utf8', $config['db']['server'], $config['db']['dbName']),
			$config['db']['user'],
			$config['db']['pass'],
			[
				PDO::ATTR_PERSISTENT => true
			]
		);
	}
	catch (PDOException $e)
	{
	}

	return false;
}

function sanitiseString(string $data): string {
	$sanitisedString = $data;
	return trim(html_entity_decode(strip_tags($sanitisedString)));
}

function truncatePostText(string $originalPostText): string {
	$explodedText = explode(" ", $originalPostText);
	$numberOfWords = 28;
	if (count($explodedText) > $numberOfWords) {
		$postText = '';
		$explodedText = explode(" ", $originalPostText);

		foreach ($explodedText as $index => $word) {
			if ($index >= $numberOfWords) {
				break;
			}

			$postText .= " " . $word;
		}
		return $postText . '…';
	} else {
		return $originalPostText;
	}
}

function preparePost(array $solrResult): array {
	$postPubDateSorter = $solrResult['post_pub_date_sorter'];
	$timestamp = strtotime($postPubDateSorter);

	$relativeCutOff = strtotime("-1 month");

	if ($timestamp > $relativeCutOff) {
		$m = new \Moment\Moment($postPubDateSorter);
		$momentFromVo = $m->fromNow();
		$displayDate = $momentFromVo->getRelative();
	} else {
		$displayDate = date('M j, Y', $timestamp);
	}

	$postDescription = $solrResult['post_description'] ?? 'No post summary was provided.';
	$postImage = $solrResult['post_image'] ?? '';
	$siteName = $solrResult['site_name'] ?? 'Anonymous';
	$postMedia = $solrResult["post_media"] ?? [];
	$postLink = $solrResult['post_link'] ?? '';
	$postTitle = $solrResult['post_title'] ?? '';
	$postId = $solrResult['id'] ?? -1;
	$siteId = $solrResult['site_id'] ?? -1;

	$siteHost = parse_url($postLink, PHP_URL_HOST);

	if (!$siteHost) {
		$siteHost = '';
	}

	if (count($postMedia) > 5) {
		$postMedia = array_slice($postMedia, 0, 5);
	}

	return [
		'id' => $postId,
		'post_image' => $postImage,
		'post_link' => $postLink,
		'post_title' => sanitiseString($postTitle),
		'post_description' => truncatePostText(sanitiseString($postDescription)),
		'post_description_full' => sanitiseString($postDescription),
		'post_pub_date_sorter' => $displayDate,
		'post_pub_date_full' => date('r', $timestamp),
		'site_id' => $siteId,
		'site_name' => sanitiseString($siteName),
		'site_host' => $siteHost,
		'video' => $solrResult["site_type"] === "Anitube",
		'site_type' => $solrResult["site_type"],
		'post_media' => $postMedia,
	];
}

function initTwig(): \Twig\Environment {
	$loader = new \Twig\Loader\FilesystemLoader('../templates');
	$twig = new \Twig\Environment($loader, [
		'cache' => '../templates_cache',
		'debug' => true,
		'strict_variables' => true,
	]);

	$twig->addGlobal('isLoggedIn', isLoggedIn());

	$filters = [
		new \Twig\TwigFilter('html_entity_decode', 'html_entity_decode'),
	];

	foreach ($filters as $filter) {
		$twig->addFilter($filter);
	}

	return $twig;
}

/**
 * @param string $oauthRedirectUri
 * @return \Google\Client
 */
function getGoogleOAuthClient(string $oauthRedirectUri): \Google\Client
{
	$client = new Google\Client();
	$client->setAuthConfig('../app-config/google_auth_credentials.json');
	$client->setRedirectUri($oauthRedirectUri);
	$client->setScopes(['email', 'profile']);
	$client->setAccessType('offline');
	return $client;
}

/**
 * @param \Google\Client $client
 * @return array
 */
function getGoogleIdTokenData(\Google\Client $client): array
{
	$tokenData = [];

	if (isset($_SESSION['id_token_token'])) {
		try {
			$client->setAccessToken($_SESSION['id_token_token']);
		} catch (InvalidArgumentException $e) {
		}
	}

	if ($client->getAccessToken()) {
		$tokenData = $client->verifyIdToken();
	}

	return $tokenData;
}

/**
 * @return bool
 */
function isLoggedIn(): bool {
	return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

function createUser(PDO $db, string $email): bool {
	$query = "INSERT INTO `users` (`email_address`) VALUES (?)";
	$prepare = $db->prepare($query);
	return $prepare->execute([$email]);
}

function updateUserLoginTime(PDO $db, int $userId, string $timestamp): bool {
	$query = "UPDATE `users` SET `modified` = ? WHERE (`pk_user_id` = ?)";
	$prepare = $db->prepare($query);
	return $prepare->execute([$timestamp, $userId]);
}

function findUser(PDO $db, string $email): int {
	$userId = -1;

	$query = "SELECT pk_user_id FROM users WHERE email_address = ?";
	$prepare = $db->prepare($query);
	$prepare->execute([$email]);
	$rows = $prepare->rowCount();

	if ($rows > 0) {
		$row = $prepare->fetch(PDO::FETCH_ASSOC);
		$userId = (int)$row['pk_user_id'];
	}

	return $userId;
}

function getPostById(PDO $db, int $id): array {
	$query = "SELECT pk_post_id, link, fk_site_id FROM posts WHERE pk_post_id = ?";
	$prepare = $db->prepare($query);
	$prepare->execute([$id]);
	$foundPost = $prepare->rowCount();

	if ($foundPost > 0) {
		return $prepare->fetch(PDO::FETCH_ASSOC);
	}

	return [];
}

function getUserSubscriptions(PDO $db, int $userId): array {
	$query = "SELECT fk_site_id FROM users_subscriptions WHERE fk_user_id = ?";
	$prepare = $db->prepare($query);
	$prepare->execute([$userId]);
	$ttlRows = $prepare->rowCount();

	if ($ttlRows > 0) {
		$rows = $prepare->fetchAll(PDO::FETCH_COLUMN);
		return array_flip($rows);
	}

	return [];
}

function logout() {
	unset($_SESSION['user_id']);
	unset($_SESSION['email']);
	unset($_SESSION['id_token_token']);
}
