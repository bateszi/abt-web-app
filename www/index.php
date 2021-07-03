<?php
require_once '../vendor/autoload.php';
require_once '_lib.php';

$config = getConfig();

$twig = initTwig();

$client = new \GuzzleHttp\Client([
	'base_uri' => $config['solrBaseUrl'],
	'timeout' => 2.0,
]);

$query = $_GET['query'] ?? '*';
$query = trim($query);

if (empty($query)) {
	$query = '*';
}

$fq = '';

$sorter = $_GET['sorter'] ?? 'post_pub_date_sorter desc';
$rows = 50;
$start = $_GET['start'] ?? 0;

$searchOptions = [
	'query' => [
		'q' => $query,
		'rows' => $rows,
		'start' => $start,
		'fq' => $fq,
	]
];

if (!isset($_GET['sorter']) && $query !== '*') {
	$sorter = '';
}

if (trim($sorter) !== '') {
	$searchOptions['query']['sort'] = trim($sorter);
}

try {
	$response = $client->request('GET', '/solr/rss/select', $searchOptions);
	$responseCode = $response->getStatusCode();
} catch (\GuzzleHttp\Exception\ServerException $e) {
	$response = false;
	$responseCode = 500;
}

$numResults = 0;
$results = [];

if ($responseCode === 200) {
	$responseBody = $response->getBody();
	$responseJson = (string)$responseBody;
	$solrResponse = json_decode($responseJson, true);

	if (!empty($solrResponse)) {
		$numResults = $solrResponse["response"]["numFound"];

		if ($numResults > 0) {
			$solrResults = $solrResponse["response"]["docs"];

			foreach ($solrResults as $solrResult) {
				$results[] = preparePost($solrResult);
			}
		}
	}
}

$nextOffset = $start + $rows;
$query = ($query === '*') ? '' : $query;

$userSubs = [];

if (isLoggedIn()) {
	$db = getDbConnection();
	$userSubs = getUserSubscriptions($db, $_SESSION['user_id']);
}

echo $twig->render('index.twig', [
	'numResults' => $numResults,
	'results' => $results,
	'query' => $query,
	'sorter' => $sorter,
	'hasMore' => $numResults > $nextOffset,
	'nextOffset' => $nextOffset,
	'pageNumber' => ($start + $rows) / $rows,
	'ttlPages' => ceil($numResults / $rows),
	'cdnBaseUrl' => $config['cdn'],
	'userSubs' => $userSubs,
]);
