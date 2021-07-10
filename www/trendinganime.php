<?php
require_once '../vendor/autoload.php';
require_once '_lib.php';

$db = getDbConnection();
$config = getConfig();

$year = (int)date('Y');

$seasons = [
	[
		'label'		=> 'Last Spring',
		'active' 	=> mktime(0, 0, 0, 4, 1, $year-1),
		'end'		=> mktime(0, 0, 0, 6, 30, $year-1),
	],
	[
		'label'		=> 'Last Summer',
		'active' 	=> mktime(0, 0, 0, 7, 1, $year-1),
		'end'		=> mktime(0, 0, 0, 9, 30, $year-1),
	],
	[
		'label'		=> 'Last Autumn',
		'active' 	=> mktime(0, 0, 0, 10, 1, $year-1),
		'end'		=> mktime(0, 0, 0, 12, 31, $year-1),
	],
	[
		'label'		=> 'Winter',
		'active' 	=> mktime(0, 0, 0, 1, 1, $year),
		'end'		=> mktime(0, 0, 0, 3, 31, $year),
	],
	[
		'label'		=> 'Spring',
		'active' 	=> mktime(0, 0, 0, 4, 1, $year),
		'end'		=> mktime(0, 0, 0, 6, 30, $year),
	],
	[
		'label'		=> 'Summer',
		'active' 	=> mktime(0, 0, 0, 7, 1, $year),
		'end'		=> mktime(0, 0, 0, 9, 30, $year),
	],
	[
		'label'		=> 'Autumn',
		'active' 	=> mktime(0, 0, 0, 10, 1, $year),
		'end'		=> mktime(0, 0, 0, 12, 31, $year),
	],
];

$currentTime = strtotime('now');
$relevantSeasons = [];

foreach ($seasons as $key => $season) {
	if ($currentTime >= $season['active'] && $currentTime <= $season['end']) {
		$relevantSeasons[] = $season;
		$relevantSeasons[] = $seasons[$key - 1];
		$relevantSeasons[] = $seasons[$key - 2];
		$relevantSeasons[] = $seasons[$key - 3];
		break;
	}
}

$requestedSeasonIndex = $_GET['s'] ?? 0;

if (isset($relevantSeasons[$requestedSeasonIndex])) {
	$requestedSeason = $relevantSeasons[$requestedSeasonIndex];
} else {
	$requestedSeasonIndex = 0;
	$requestedSeason = $relevantSeasons[0];
}

$searchOptions = [
	'query' => [
		'facet.field' => 'post_media',
		'facet.limit' => 100,
		'facet.mincount' => 1,
		'facet.sort' => 'count',
		'facet' => 'on',
		'fl' => 'id',
		'fq' => sprintf(
			'post_media_start_date:[%s TO %s]',
			date('Y-m-d\T00:00:00\Z', $requestedSeason['active']),
			date('Y-m-d\T00:00:00\Z', $requestedSeason['end'])
		),
		'q' => '*:*',
		'rows' => 1,
		'sort' => 'post_pub_date_sorter desc',
		'start' => 0,
	]
];
try {
	$client = new \GuzzleHttp\Client([
		'base_uri' => $config['solrBaseUrl'],
		'timeout' => 2.0,
	]);
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
	'period' => $requestedSeasonIndex,
	'relevantSeasons' => $relevantSeasons,
]);
