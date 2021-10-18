<?php

require __DIR__ . '/../vendor/autoload.php';

/**
 * @param string $url1
 * @param string $url2
 * @return Psr\Http\Message\ResponseInterface[]
 * @throws Exception when HTTP request fails
 */
function requestHttpMultiple($url1, $url2)
{
    // This example uses an HTTP client
    $browser = new React\Http\Browser();

    // set up two parallel requests
    $promises = array(
        $browser->get($url1),
        $browser->get($url2)
    );

    try {
        // keep the loop running (i.e. block) until all responses arrive
        $allResults = Clue\React\Block\awaitAll($promises);

        // promise successfully fulfilled with $allResults
        return $allResults;
    } catch (Exception $exception) {
        // promise rejected with $exception
        throw $exception;
    }
}

$all = requestHttpMultiple('http://www.google.com/', 'http://www.google.co.uk/');
echo 'First promise resolved with: ' . $all[0]->getBody() . PHP_EOL;
echo 'Second promise resolved with: ' . $all[1]->getBody() . PHP_EOL;
