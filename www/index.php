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

$customFrom = $_GET['customFrom'] ?? '';
$customTo = $_GET['customTo'] ?? '';

if (!empty($customFrom) && !empty($customTo)) {
	$customFromTimestamp = @strtotime($customFrom);
	$customToTimestamp = @strtotime($customTo);

	if ($customFromTimestamp !== false && $customToTimestamp !== false) {
		$dateRangeFilter = sprintf(
			"post_pub_date_range_utc:[%s TO %s]",
			date('Y-m-d', $customFromTimestamp),
			date('Y-m-d', $customToTimestamp)
		);
		$fq .= $dateRangeFilter;
	} else {
		$customFrom = '';
		$customTo = '';
	}
}

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

$statsQuery = '{
	"query": "*:*",
	"fields": "id,post_title,site_name",
	"filter": "post_pub_date_range_utc:[NOW-7DAY TO NOW]",
	"facet": {
		"sites": {
			"type": "terms",
			"field": "site_name",
			"sort": "total_views desc",
			"limit": 10,
			"facet": {
				"total_views": "sum(view_count)"
			}
		},
		"trending_media": {
			"type": "terms",
			"field": "post_media",
			"limit": 10
		}
	},
	"limit": 10,
	"sort": "view_count DESC"
}';

$processedStats = [];

$statsResponse = $client->post('/solr/rss/query', [
	'headers' => [
		'Content-Type' => 'application/json'
	],
	'body' => $statsQuery
]);

if ($statsResponse->getStatusCode() === 200) {
	$statsJson = (string)$statsResponse->getBody();
	$statsDecoded = json_decode($statsJson, true);

	if ($statsDecoded['response']['numFound'] > 0) {
		foreach ($statsDecoded['response']['docs'] as $doc) {
			$processedStats['trending_posts'][$doc['post_title']] = ['id' => $doc['id'], 'site_name' => $doc['site_name']];
		}

		foreach ($statsDecoded['facets']['trending_media']['buckets'] as $media) {
			$processedStats['trending_media'][$media['val']] = $media['count'];
		}

		foreach ($statsDecoded['facets']['sites']['buckets'] as $site) {
			$processedStats['trending_bloggers'][$site['val']] = $site['total_views'];
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
	'stats' => $processedStats,
	'cdnBaseUrl' => $config['cdn'],
	'customFrom' => $customFrom,
	'customTo' => $customTo,
	'userSubs' => $userSubs,
]);
