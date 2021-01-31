<?php
require_once '../vendor/autoload.php';
require_once '_lib.php';

$config = require_once '../app-config/config.php';

$twig = initTwig();

$postId = $_GET['id'] ?? -1;

$client = new \GuzzleHttp\Client([
	'base_uri' => $config['solrBaseUrl'],
	'timeout' => 2.0,
]);

$searchOptions = [
	'query' => [
		'q' => '*',
		'fq' => sprintf('id:%d', $postId),
		'mlt' => 'true',
		'mlt.mintf' => '1',
		'mlt.count' => '50',
		'mlt.fl' => 'post_title',
	]
];

try {
	$response = $client->request('GET', '/solr/rss/select', $searchOptions);
	$responseCode = $response->getStatusCode();
} catch (\GuzzleHttp\Exception\ServerException $e) {
	$response = false;
	$responseCode = 500;
}

$tplVars = [
	'post' => null,
	'results' => null,

	// required by partials
	'query' => '',
	'cdnBaseUrl' => $config['cdn'],
];

if ($responseCode === 200) {
	$responseBody = $response->getBody();
	$responseJson = (string)$responseBody;
	$solrResponse = json_decode($responseJson, true);

	if (!empty($solrResponse)) {
		$numResults = $solrResponse["response"]["numFound"];

		if ($numResults > 0) {
			$solrDoc = $solrResponse["response"]["docs"][0];
			$tplVars['post'] = preparePost($solrDoc);

			$relatedPostsInfo = $solrResponse["moreLikeThis"][$postId];

			if ($relatedPostsInfo['numFound'] > 0) {
				$relatedPosts = [];

				foreach ($relatedPostsInfo['docs'] as $relatedPostDoc) {
					$relatedPosts[] = preparePost($relatedPostDoc);
				}

				$tplVars['results'] = $relatedPosts;
			}
		}
	}
}

echo $twig->render('post.twig', $tplVars);
