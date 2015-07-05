# clue/block-react [![Build Status](https://travis-ci.org/clue/php-block-react.svg?branch=master)](https://travis-ci.org/clue/php-block-react)

Lightweight library that eases integrating async components built for
[React PHP](http://reactphp.org/) in a traditional, blocking environment.

> Note: This project is in beta stage! Feel free to report any issues you encounter.

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
    $blocker = new Blocker($loop);
    
    // this example uses an HTTP client
    // this could be pretty much everything that binds to an event loop
    $browser = new Clue\React\Buzz\Browser($loop);
    
    // set up two parallel requests
    $request1 = $browser->get('http://www.google.com/');
    $request2 = $browser->get('http://www.google.co.uk/');
    
    // keep the loop running (i.e. block) until the first response arrives
    $fasterResponse = $blocker->awaitAny(array($request1, $request2));
    
    return $fasterResponse->getBody();
}
```

## Usage

### Blocker

The `Blocker` is responsible for orchestrating the
[`EventLoop`](https://github.com/reactphp/event-loop#usage)
in order to make it run (block) until your conditions are fulfilled.

```php
$loop = React\EventLoop\Factory::create();
$blocker = new Blocker($loop);
```

#### sleep()

The `sleep($seconds)` method can be used to wait/sleep for $time seconds.

#### await()

The `await(PromiseInterface $promise)` method can be used to block waiting for the given $promise to resolve.

#### awaitAny()

The `awaitAny(array $promises)` method can be used to wait for ANY of the given promises to resolve.

#### awaitAll()

The `awaitAll(array $promises)` method can be used to wait for ALL of the given promises to resolve.

## Install

The recommended way to install this library is [through composer](http://getcomposer.org). [New to composer?](http://getcomposer.org/doc/00-intro.md)

```JSON
{
    "require": {
        "clue/block-react": "~0.2.0"
    }
}
```

## License

MIT
