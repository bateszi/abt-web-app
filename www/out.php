<?php
require_once '../vendor/autoload.php';
require_once '_lib.php';

function getPostById(PDO $db, int $id): array {
	$query = "SELECT pk_post_id, link, view_count FROM posts WHERE pk_post_id = ?";
	$prepare = $db->prepare($query);
	$prepare->execute([$id]);
	$foundPost = $prepare->rowCount();

	if ($foundPost > 0) {
		return $prepare->fetch(PDO::FETCH_ASSOC);
	}

	return [];
}

function updatedPostViewCount(PDO $db, int $updatedViewCount, int $postId) {
	$query = "UPDATE posts SET view_count = ? WHERE pk_post_id = ?";
	$prepare = $db->prepare($query);
	$prepare->execute([$updatedViewCount, $postId]);
}

$db = getDbConnection();

$postId = $_GET['id'] ?? -1;

if ($postId > -1) {
	$post = getPostById($db, $postId);

	if (!empty($post)) {
		$updatedPostViewCount = ($post['view_count']+1);

		updatedPostViewCount($db, $updatedPostViewCount, $postId);

		header('Location: ' . $post['link']);
		exit();
	}
}

header('Location: ' . $_SERVER["REQUEST_URI"]);