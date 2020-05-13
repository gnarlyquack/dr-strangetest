<?php

while (true) {
    $prompt = 'Check for a new version of EasyTest (requires an internet connection)? [Y/n] ';
    \fwrite(\STDOUT, $prompt);
    $response = \strtolower(\trim(\fgets(\STDIN)));
    if ('' === $response || 'y' === $response) {
        break;
    }
    if ('n' === $response) {
        exit();
    }
    \fwrite(\STDOUT, "Please enter 'y' or 'n'.\n");
}

echo "would do update....\n";

if (false) {
$opts = array(
    'http' => array(
        'method' => 'GET',
        'header' => array(
            'Accept: application/vnd.github.v3+json',
            //'Connection: close',
        ),
        'user_agent' => 'EasyTest',
        //'protocol_version' => 1.1,
    ),
);
$http = \stream_context_create($opts);
$result = \file_get_contents(
    'https://api.github.com/repos/gnarlyquack/easytest/releases/latest',
    false, $http
);
echo "response:\n", \print_r($http_response_header, true), "\n";
$result = \json_decode($result);
$version = \ltrim($result->tag_name, 'vV');
echo "current version: $version\n";

if (\version_compare('0.2.2', $version, '<')) {
    echo "Would update\n";
    foreach ($result->assets as $asset) {
        if ('easytest.phar' === $asset->name) {
            $source = \fopen($asset->browser_download_url, 'rb');
            \file_put_contents('easytest.phar', $source);
            \chmod('easytest.phar', 0744);
            break;
        }
    }
}
}
