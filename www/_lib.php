<?php
function getDbConnection() {
	$pathToConfig = __DIR__ . '/../app-config/config.php';
	$config = require $pathToConfig;

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
	$m = new \Moment\Moment($solrResult['post_pub_date_sorter']);
	$momentFromVo = $m->fromNow();

	$postDescription = $solrResult['post_description'] ?? 'No post summary was provided.';
	$postImage = $solrResult['post_image'] ?? '';
	$siteName = $solrResult['site_name'] ?? 'Anonymous';
	$postMedia = $solrResult["post_media"] ?? [];
	$postLink = $solrResult['post_link'] ?? '';
	$postTitle = $solrResult['post_title'] ?? '';
	$postId = $solrResult['id'] ?? -1;

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
		'post_pub_date_sorter' => $momentFromVo->getRelative(),
		'post_pub_date_full' => date('r', strtotime($solrResult['post_pub_date_sorter'])),
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

	$filters = [
		new \Twig\TwigFilter('html_entity_decode', 'html_entity_decode'),
	];

	foreach ($filters as $filter) {
		$twig->addFilter($filter);
	}

	return $twig;
}