# clue/block-react [![Build Status](https://travis-ci.org/clue/php-block-react.svg?branch=master)](https://travis-ci.org/clue/php-block-react)

Lightweight library that eases integrating async components built for
[React PHP](http://reactphp.org/) in a traditional, blocking environment.

## Introduction

[React PHP](http://reactphp.org/) provides you a great set of base components and
a huge ecosystem of third party libraries in order to perform async operations.
The event-driven paradigm and asynchronous processing of any number of streams
in real time enables you to build a whole new set of application on top of it.
This is great for building modern, scalable applications from scratch and will
likely result in you relying on a whole new software architecture.

But let's face it: Your day-to-day business is unlikely to allow you to build
everything from scratch and ditch your existing production environment.

This is where this library comes into play:

*Let's block React PHP*

More specifically, this library eases the pain of integrating async components
into your traditional, synchronous (blocking) application stack.

### Quickstart example

The following example code demonstrates how this library can be used along with
an [async HTTP client](https://github.com/clue/php-buzz-react) to process two
non-blocking HTTP requests and block until the first (faster) one resolves.

```php
function blockingExample()
{
    // use a unique event loop instance for all parallel operations
    $loop = React\EventLoop\Factory::create();
    
    // this example uses an HTTP client
    // this could be pretty much everything that binds to an event loop
    $browser = new Clue\React\Buzz\Browser($loop);
    
    // set up two parallel requests
    $request1 = $browser->get('http://www.google.com/');
    $request2 = $browser->get('http://www.google.co.uk/');
    
    // keep the loop running (i.e. block) until the first response arrives
    $fasterResponse = Block\awaitAny(array($request1, $request2), $loop);
    
    return $fasterResponse->getBody();
}
```

## Usage

This lightweight library consists only of a few simple functions.
All functions reside under the `Clue\React\Block` namespace.

The below examples assume you use an import statement similar to this:

```php
use Clue\React\Block;

Block\await(…);
```

Alternatively, you can also refer to them with their fully-qualified name:

```php
\Clue\React\Block\await(…);
``` 

### EventLoop

Each function is responsible for orchestrating the
[`EventLoop`](https://github.com/reactphp/event-loop#usage)
in order to make it run (block) until your conditions are fulfilled.

```php
$loop = React\EventLoop\Factory::create();
```

#### sleep()

The `sleep($seconds, LoopInterface $loop)` method can be used to wait/sleep for $time seconds.

```php
Block\sleep(1.5, $loop);
```

Similar to PHP's [`sleep()`](http://php.net/sleep) function.
Allows for floating point.
Loop can perform other (async) tasks.

#### await()

The `await(PromiseInterface $promise, LoopInterface $loop)` method can be used to block waiting for the given $promise to resolve.

```php
$result = Block\await($promise, $loop);
```

```php
try {
    $value = Block\await($promise, $loop);
    // promise successfully fulfilled with $value
    echo 'Result: ' . $value;
} catch (Exeption $exception) {
    // promise rejected with $exception
    echo 'ERROR: ' . $exception->getMessage();
}
```

#### awaitAny()

The `awaitAny(array $promises, LoopInterface $loop)` method can be used to wait for ANY of the given promises to resolve.

```php
$promises = array(
    $promise1,
    $promise2
);

$result = Block\awaitAny($promises, $loop);
```

Once the first promise is resolved, this will try to cancel() all
 * remaining promises and return whatever the first promise resolves to.
 *
 * If ALL promises fail to resolve, this will fail and throw an Exception.

#### awaitAll()

The `awaitAll(array $promises, LoopInterface $loop)` method can be used to wait for ALL of the given promises to resolve.


Once the last promise resolves, this will return an array with whatever
 * each promise resolves to. Array keys will be left intact, i.e. they can
 * be used to correlate the return array to the promises passed.
 *
 * If ANY promise fails to resolve, this will try to cancel() all
 * remaining promises and throw an Exception.

## Install

The recommended way to install this library is [through composer](http://getcomposer.org). [New to composer?](http://getcomposer.org/doc/00-intro.md)

```JSON
{
    "require": {
        "clue/block-react": "~0.3.0"
    }
}
```

## License

MIT
