<?php

require __DIR__ . '/../vendor/autoload.php';

/**
 * @param string $url1
 * @param string $url2
 * @return Psr\Http\Message\ResponseInterface
 * @throws Exception when HTTP request fails
 */
function requestHttpFastestOfMultiple($url1, $url2)
{
    // This example uses an HTTP client
    $browser = new React\Http\Browser();

    // set up two parallel requests
    $promises = array(
        $browser->get($url1),
        $browser->get($url2)
    );

    try {
        // keep the loop running (i.e. block) until the first response arrives
        $fasterResponse = Clue\React\Block\awaitAny($promises);

        // promise successfully fulfilled with $fasterResponse
        return $fasterResponse;
    } catch (Exception $exception) {
        // promise rejected with $exception
        throw $exception;
    }
}

$first = requestHttpFastestOfMultiple('http://www.google.com/', 'http://www.google.co.uk/');
echo $first->getBody();
