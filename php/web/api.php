<?php

require_once __DIR__ . '/../vendor/autoload.php';

$app = new ApiApplication();

// $app['http_cache']->run();
$app->run();