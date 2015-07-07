<?php

use oat\tao\solarium\SolariumSearch;
use oat\tao\model\search\SearchService;

$parms = $argv;
array_shift($parms);

if (count($parms) != 1) {
	echo 'Usage: '.__FILE__.' TAOROOT'.PHP_EOL;
	die(1);
}

$root = rtrim(array_shift($parms), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
$rawStart = $root.'tao'.DIRECTORY_SEPARATOR.'includes'.DIRECTORY_SEPARATOR.'raw_start.php';

if (!file_exists($rawStart)) {
    echo 'Tao not found at "'.$rawStart.'"'.PHP_EOL;
    die(1);
}

require_once $rawStart;

if (!class_exists('oat\\tao\\solarium\\SolariumSearch')) {
    echo 'Tao Solarium Search not found'.PHP_EOL;
    die(1);
}

$config = array(
    'endpoint' => array(
        'localhost' => array(
            'host' => '127.0.0.1',
            'port' => 8983,
            'path' => '/solr/tao',//your active core should be specified here
        )
    )
);

$search = new SolariumSearch($config);
try {
    $result = $search->query('');
} catch (Solarium\Exception\HttpException $e) {
    echo "Solr server not found: ".$e->getMessage().PHP_EOL;
    die(1);
}

$success = SearchService::setSearchImplementation($search);

echo "Switched to Solr search using Solarium".PHP_EOL;
die(0);
