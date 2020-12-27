<?php
require_once '../vendor/autoload.php';
require_once '_lib.php';

function getPostById(PDO $db, int $id): array {
	$query = "SELECT pk_post_id, link FROM posts WHERE pk_post_id = ?";
	$prepare = $db->prepare($query);
	$prepare->execute([$id]);
	$foundPost = $prepare->rowCount();

	if ($foundPost > 0) {
		return $prepare->fetch(PDO::FETCH_ASSOC);
	}

	return [];
}

function updatedPostViewCount(PDO $db, int $postId) {
	$query = "UPDATE posts SET view_count = view_count + 1 WHERE pk_post_id = ?";
	$prepare = $db->prepare($query);
	$prepare->execute([$postId]);
}

function updateSolrDoc(string $solrUrl, int $postId) {
	$client = new \GuzzleHttp\Client([
		'base_uri' => $solrUrl,
		'timeout' => 2.0,
	]);

	$json = sprintf('[{"id": "%d", "view_count": {"inc": 1}}]', $postId);

	$requestOptions = [
		'query' => [
			'commit' => 'true',
		],
		'headers' => [
			'Content-Type' => 'application/json'
		],
		'body' => $json
	];

	$client->post('/solr/rss/update', $requestOptions);
}

$config = require_once '../app-config/config.php';

$db = getDbConnection();

$postId = $_GET['id'] ?? -1;

if ($postId > -1) {
	$post = getPostById($db, $postId);

	if (!empty($post)) {
		updatedPostViewCount($db, $postId);
		updateSolrDoc($config['solrBaseUrl'], $postId);

		header('Location: ' . $post['link']);
		exit();
	}
}

header('Location: ' . $_SERVER["REQUEST_URI"]);