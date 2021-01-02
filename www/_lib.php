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
