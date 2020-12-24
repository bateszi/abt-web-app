<?php
require_once '../vendor/autoload.php';

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

$config = require_once '../app-config/config.php';

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

$functions = [
	new \Twig\TwigFunction('randColor', 'randColor')
];

foreach ($functions as $function) {
	$twig->addFunction($function);
}

$client = new \GuzzleHttp\Client([
	'base_uri' => $config['solrBaseUrl'],
	'timeout' => 2.0,
]);

$query = $_GET['query'] ?? '*';
$query = trim($query);

if (empty($query)) {
	$query = '*';
}

$sorter = $_GET['sorter'] ?? 'post_pub_date_sorter desc';
$rows = 20;
$start = $_GET['start'] ?? 0;

$searchOptions = [
	'query' => [
		'q' => $query,
		'rows' => $rows,
		'start' => $start,
	]
];

if (!isset($_GET['sorter']) && $query !== '*') {
	$sorter = '';
}

if (trim($sorter) !== '') {
	$searchOptions['query']['sort'] = trim($sorter);
}

$response = $client->request('GET', '/solr/rss/select', $searchOptions);

$responseCode = $response->getStatusCode();

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

				$results[] = [
					'id' => $postId,
					'post_image' => $postImage,
					'post_link' => $postLink,
					'post_title' => sanitiseString($postTitle),
					'post_description' => truncatePostText(sanitiseString($postDescription)),
					'post_pub_date_sorter' => $momentFromVo->getRelative(),
					'site_name' => sanitiseString($siteName),
					'site_host' => $siteHost,
					'video' => $solrResult["site_type"] === "Anitube",
					'site_type' => $solrResult["site_type"],
					'post_media' => $postMedia,
				];
			}
		}
	}
}

$processedStats = [];
$isHomepage = $_SERVER['REQUEST_URI'] === '/';

if ($isHomepage) {
	$statsSolrParams = [
		'q' => 'post_pub_date_range_utc:[NOW-30DAYS TO NOW]',
		'start' => 0,
		'rows' => 0,
		'sort' => 'post_pub_date_sorter desc',
		'facet' => 'on',
		'facet.mincount' => 1,
		'f.site_name.facet.limit' => 20,
		'f.post_media.facet.limit' => 20,
//		'facet.range' => 'post_pub_date_range_utc',
//		'f.post_pub_date_range_utc.facet.range.start' => 'NOW/DAY-20DAYS',
//		'f.post_pub_date_range_utc.facet.range.end' => 'NOW',
//		'f.post_pub_date_range_utc.facet.range.gap' => '+1DAY',
	];

	$statsSolrParams = http_build_query($statsSolrParams);
	$statsSolrParams .= '&facet.field=post_media&facet.field=site_name';
	$statsResponse = $client->request('GET', '/solr/rss/select', ['query' => $statsSolrParams]);

	if ($statsResponse->getStatusCode() === 200) {
		$statsJson = (string)$statsResponse->getBody();
		$statsDecoded = json_decode($statsJson, true);
		$facetFields = $statsDecoded["facet_counts"]["facet_fields"];

		foreach ($facetFields as $fieldName => $facetValues) {
			foreach ($facetValues as $key => $facetValue) {
				if ($key % 2 == 0) {
					$processedStats[$fieldName][$facetValue] = $facetValues[$key+1];
				}
			}
		}
	}
}

$nextOffset = $start + $rows;
$query = ($query === '*') ? '' : $query;

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
	'isHomepage' => $isHomepage,
]);
