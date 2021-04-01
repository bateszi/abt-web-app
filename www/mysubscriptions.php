<?php
require_once '../vendor/autoload.php';
require_once '_lib.php';

if (isLoggedIn()) {

	$config = getConfig();
	$db = getDbConnection();
	$sites = getUserSubscriptions($db, $_SESSION['user_id']);

	$query = '*';
	$sorter = 'post_pub_date_sorter desc';
	$numResults = 0;
	$results = [];
	$rows = 50;
	$start = $_GET['start'] ?? 0;
	$nextOffset = $start + $rows;

	if (!empty($sites)) {
		$sitesFlipped = array_flip($sites);
		$siteIdString = implode(" ", $sitesFlipped);

		$client = new \GuzzleHttp\Client([
			'base_uri' => $config['solrBaseUrl'],
			'timeout' => 2.0,
		]);

		$fq = sprintf('site_id:(%s)', $siteIdString);

		$searchOptions = [
			'query' => [
				'q' => $query,
				'rows' => $rows,
				'start' => $start,
				'fq' => $fq,
				'sort' => $sorter,
			]
		];

		try {
			$response = $client->request('GET', '/solr/rss/select', $searchOptions);
			$responseCode = $response->getStatusCode();
		} catch (\GuzzleHttp\Exception\ServerException $e) {
			$response = false;
			$responseCode = 500;
		}

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
	}

	$twig = initTwig();

	echo $twig->render('mysubscriptions.twig', [
		'numResults' => $numResults,
		'results' => $results,
		'hasMore' => $numResults > $nextOffset,
		'query' => '',
		'sorter' => $sorter,
		'nextOffset' => $nextOffset,
		'pageNumber' => ($start + $rows) / $rows,
		'ttlPages' => ceil($numResults / $rows),
		'cdnBaseUrl' => $config['cdn'],
		'userSubs' => $sites,
	]);

} else {
	header('Location: /login.php');
	return;
}
