<?php

require __DIR__ . '/../vendor/autoload.php';

/**
 * @param string $url
 * @return Psr\Http\Message\ResponseInterface
 * @throws Exception when HTTP request fails
 */
function requestHttp($url)
{
    // This example uses an HTTP client
    $browser = new React\Http\Browser();

    // set up one request
    $promise = $browser->get($url);

    try {
        // keep the loop running (i.e. block) until the response arrives
        $result = Clue\React\Block\await($promise);

        // promise successfully fulfilled with $result
        return $result;
    } catch (Exception $exception) {
        // promise rejected with $exception
        throw $exception;
    }
}

$response = requestHttp('http://www.google.com/');
echo $response->getBody();
