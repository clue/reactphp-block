<?php

require __DIR__ . '/../vendor/autoload.php';

/**
 * @param string $url
 * @return Psr\Http\Message\ResponseInterface
 * @throws Exception when HTTP request fails
 */
function requestHttp($url)
{
    // use a unique event loop instance for this operation
    $loop = React\EventLoop\Factory::create();

    // This example uses an HTTP client
    $browser = new React\Http\Browser($loop);

    // set up one request
    $promise = $browser->get($url);

    try {
        // keep the loop running (i.e. block) until the response arrives
        $result = Clue\React\Block\await($promise, $loop);

        // promise successfully fulfilled with $result
        return $result;
    } catch (Exception $exception) {
        // promise rejected with $exception
        throw $exception;
    }
}

$response = requestHttp('http://www.google.com/');
echo $response->getBody();
