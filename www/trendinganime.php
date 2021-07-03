<?php
require_once '../vendor/autoload.php';
require_once '_lib.php';

$db = getDbConnection();
$config = getConfig();

$client = new \GuzzleHttp\Client([
	'base_uri' => $config['solrBaseUrl'],
	'timeout' => 2.0,
]);

$year = (int)date('Y');

$period = $_GET['r'] ?? "3m";
$solrPeriod = "";

switch ($period) {
	case "3m":
		$solrPeriod = "3MONTHS";
		$minCount = 20;
		break;
	case "12m":
		$solrPeriod = "12MONTHS";
		$minCount = 80;
		break;
	default:
		$solrPeriod = "6MONTHS";
		$minCount = 40;
}

$searchOptions = [
	'query' => [
		'facet.field' => 'post_media',
		'facet.limit' => -1,
		'facet.mincount' => $minCount,
		'facet.sort' => 'count',
		'facet' => 'on',
		'fl' => 'id',
		'fq' => sprintf('post_media_start_date:[NOW-%s TO NOW+3MONTHS]', $solrPeriod),
		'q' => '*:*',
		'rows' => 1,
		'sort' => 'post_pub_date_sorter desc',
		'start' => 0,
	]
];
try {
	$response = $client->request('GET', '/solr/rss/select', $searchOptions);
	$responseCode = $response->getStatusCode();
} catch (\GuzzleHttp\Exception\ServerException $e) {
	$response = false;
	$responseCode = 500;
}

$relevantAnimeSeries = [];

if ($responseCode === 200) {
	$responseBody = $response->getBody();
	$responseJson = (string)$responseBody;
	$solrResponse = json_decode($responseJson, true);

	if (isset(
		$solrResponse['facet_counts'],
		$solrResponse['facet_counts']['facet_fields'],
		$solrResponse['facet_counts']['facet_fields']['post_media']
	)) {
		$postMedia = $solrResponse['facet_counts']['facet_fields']['post_media'];

		foreach ($postMedia as $index => $item) {
			if ($index % 2 === 0) {
				$relevantAnimeSeries[] = [
					'title' => $item,
					'count' => $postMedia[$index+1],
				];
			}
		}
	}
}

$twig = initTwig();

echo $twig->render('trendinganime.twig', [
	'relevantAnimeSeries' => $relevantAnimeSeries,
	'period' => $period,
]);
