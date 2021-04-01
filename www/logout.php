<?php
require_once '../vendor/autoload.php';
require_once '_lib.php';

if (isLoggedIn()) {
	logout();
}

header('Location: /index.php');
return;
