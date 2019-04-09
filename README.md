# clue/reactphp-block [![Build Status](https://travis-ci.org/clue/reactphp-block.svg?branch=master)](https://travis-ci.org/clue/reactphp-block)

Lightweight library that eases integrating async components built for
[ReactPHP](https://reactphp.org/) in a traditional, blocking environment.

[ReactPHP](https://reactphp.org/) provides you a great set of base components and
a huge ecosystem of third party libraries in order to perform async operations.
The event-driven paradigm and asynchronous processing of any number of streams
in real time enables you to build a whole new set of application on top of it.
This is great for building modern, scalable applications from scratch and will
likely result in you relying on a whole new software architecture.

But let's face it: Your day-to-day business is unlikely to allow you to build
everything from scratch and ditch your existing production environment.
This is where this library comes into play:

*Let's block ReactPHP*
More specifically, this library eases the pain of integrating async components
into your traditional, synchronous (blocking) application stack.

**Table of contents**

* [Quickstart example](#quickstart-example)
* [Usage](#usage)
  * [sleep()](#sleep)
  * [await()](#await)
  * [awaitAny()](#awaitany)
  * [awaitAll()](#awaitall)
* [Install](#install)
* [Tests](#tests)
* [License](#license)

### Quickstart example

The following example code demonstrates how this library can be used along with
an [async HTTP client](https://github.com/clue/reactphp-buzz) to process two
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

### sleep()

The `sleep($seconds, LoopInterface $loop)` method can be used to wait/sleep for $time seconds.

```php
Block\sleep(1.5, $loop);
```

The $time value will be used as a timer for the loop so that it keeps running
until the timeout triggers.
This implies that if you pass a really small (or negative) value, it will still
start a timer and will thus trigger at the earliest possible time in the future.

While this may look similar to PHP's [`sleep()`](https://www.php.net/sleep) function,
it's actual way more powerful:
Instead of making the whole process sleep and handing over control to your operating system,
this function actually executes the loop in the meantime.
This is particularly useful if you've attached more async tasks to the same loop instance.
If there are no other (async) tasks, this will behave similar to `sleep()`.

### await()

The `await(PromiseInterface $promise, LoopInterface $loop, $timeout = null)`
function can be used to block waiting for the given $promise to resolve.

```php
$result = Block\await($promise, $loop);
```

Once the promise is resolved, this will return whatever the promise resolves to.

Once the promise is rejected, this will throw whatever the promise rejected with.
If the promise did not reject with an `Exception`, then this function will
throw an `UnexpectedValueException` instead.

```php
try {
    $value = Block\await($promise, $loop);
    // promise successfully fulfilled with $value
    echo 'Result: ' . $value;
} catch (Exception $exception) {
    // promise rejected with $exception
    echo 'ERROR: ' . $exception->getMessage();
}
```

If no $timeout is given and the promise stays pending, then this will
potentially wait/block forever until the promise is settled.

If a $timeout is given and the promise is still pending once the timeout
triggers, this will `cancel()` the promise and throw a `TimeoutException`.
This implies that if you pass a really small (or negative) value, it will still
start a timer and will thus trigger at the earliest possible time in the future.

### awaitAny()

The `awaitAny(array $promises, LoopInterface $loop, $timeout = null)`
function can be used to wait for ANY of the given promises to resolve.

```php
$promises = array(
    $promise1,
    $promise2
);

$firstResult = Block\awaitAny($promises, $loop);

echo 'First result: ' . $firstResult;
```

Once the first promise is resolved, this will try to `cancel()` all
remaining promises and return whatever the first promise resolves to.

If ALL promises fail to resolve, this will fail and throw an `Exception`.

If no $timeout is given and either promise stays pending, then this will
potentially wait/block forever until the first promise is settled.

If a $timeout is given and either promise is still pending once the timeout
triggers, this will `cancel()` all pending promises and throw a `TimeoutException`.
This implies that if you pass a really small (or negative) value, it will still
start a timer and will thus trigger at the earliest possible time in the future.

### awaitAll()

The `awaitAll(array $promises, LoopInterface $loop, $timeout = null)`
function can be used to wait for ALL of the given promises to resolve.

```php
$promises = array(
    $promise1,
    $promise2
);

$allResults = Block\awaitAll($promises, $loop);

echo 'First promise resolved with: ' . $allResults[0];
```

Once the last promise resolves, this will return an array with whatever
each promise resolves to. Array keys will be left intact, i.e. they can
be used to correlate the return array to the promises passed.

If ANY promise fails to resolve, this will try to `cancel()` all
remaining promises and throw an `Exception`.
If the promise did not reject with an `Exception`, then this function will
throw an `UnexpectedValueException` instead.

If no $timeout is given and either promise stays pending, then this will
potentially wait/block forever until the last promise is settled.

If a $timeout is given and either promise is still pending once the timeout
triggers, this will `cancel()` all pending promises and throw a `TimeoutException`.
This implies that if you pass a really small (or negative) value, it will still
start a timer and will thus trigger at the earliest possible time in the future.

## Install

The recommended way to install this library is [through Composer](https://getcomposer.org).
[New to Composer?](https://getcomposer.org/doc/00-intro.md)

This project follows [SemVer](https://semver.org/).
This will install the latest supported version:

```bash
$ composer require clue/block-react:^1.3.1
```

See also the [CHANGELOG](CHANGELOG.md) for details about version upgrades.

This project aims to run on any platform and thus does not require any PHP
extensions and supports running on legacy PHP 5.3 through current PHP 7+ and
HHVM.
It's *highly recommended to use PHP 7+* for this project.

## Tests

To run the test suite, you first need to clone this repo and then install all
dependencies [through Composer](https://getcomposer.org):

```bash
$ composer install
```

To run the test suite, go to the project root and run:

```bash
$ php vendor/bin/phpunit
```

## License

This project is released under the permissive [MIT license](LICENSE).

> Did you know that I offer custom development services and issuing invoices for
  sponsorships of releases and for contributions? Contact me (@clue) for details.
