# clue/reactphp-block

[![CI status](https://github.com/clue/reactphp-block/workflows/CI/badge.svg)](https://github.com/clue/reactphp-block/actions)
[![installs on Packagist](https://img.shields.io/packagist/dt/clue/block-react?color=blue&label=installs%20on%20Packagist)](https://packagist.org/packages/clue/block-react)

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

* [Support us](#support-us)
* [Quickstart example](#quickstart-example)
* [Usage](#usage)
    * [sleep()](#sleep)
    * [await()](#await)
    * [awaitAny()](#awaitany)
    * [awaitAll()](#awaitall)
* [Install](#install)
* [Tests](#tests)
* [License](#license)

## Support us

We invest a lot of time developing, maintaining and updating our awesome
open-source projects. You can help us sustain this high-quality of our work by
[becoming a sponsor on GitHub](https://github.com/sponsors/clue). Sponsors get
numerous benefits in return, see our [sponsoring page](https://github.com/sponsors/clue)
for details.

Let's take these projects to the next level together! 🚀

### Quickstart example

The following example code demonstrates how this library can be used along with
an [async HTTP client](https://github.com/reactphp/http#client-usage) to process two
non-blocking HTTP requests and block until the first (faster) one resolves.

```php
function blockingExample()
{
    // this example uses an HTTP client
    // this could be pretty much everything that binds to an event loop
    $browser = new React\Http\Browser();

    // set up two parallel requests
    $request1 = $browser->get('http://www.google.com/');
    $request2 = $browser->get('http://www.google.co.uk/');

    // keep the loop running (i.e. block) until the first response arrives
    $fasterResponse = Clue\React\Block\awaitAny(array($request1, $request2));

    return $fasterResponse->getBody();
}
```

## Usage

This lightweight library consists only of a few simple functions.
All functions reside under the `Clue\React\Block` namespace.

The below examples refer to all functions with their fully-qualified names like this:

```php
Clue\React\Block\await(…);
```

As of PHP 5.6+ you can also import each required function into your code like this:

```php
use function Clue\React\Block\await;

await(…);
```

Alternatively, you can also use an import statement similar to this:

```php
use Clue\React\Block;

Block\await(…);
```

### sleep()

The `sleep(float $seconds, ?LoopInterface $loop = null): void` function can be used to
wait/sleep for `$time` seconds.

```php
Clue\React\Block\sleep(1.5, $loop);
```

This function will only return after the given `$time` has elapsed. In the
meantime, the event loop will run any other events attached to the same loop
until the timer fires. If there are no other events attached to this loop,
it will behave similar to the built-in [`sleep()`](https://www.php.net/manual/en/function.sleep.php).

Internally, the `$time` argument will be used as a timer for the loop so that
it keeps running until this timer triggers. This implies that if you pass a
really small (or negative) value, it will still start a timer and will thus
trigger at the earliest possible time in the future.

This function takes an optional `LoopInterface|null $loop` parameter that can be used to
pass the event loop instance to use. You can use a `null` value here in order to
use the [default loop](https://github.com/reactphp/event-loop#loop). This value
SHOULD NOT be given unless you're sure you want to explicitly use a given event
loop instance.

Note that this function will assume control over the event loop. Internally, it
will actually `run()` the loop until the timer fires and then calls `stop()` to
terminate execution of the loop. This means this function is more suited for
short-lived program executions when using async APIs is not feasible. For
long-running applications, using event-driven APIs by leveraging timers
is usually preferable.

### await()

The `await(PromiseInterface $promise, ?LoopInterface $loop = null, ?float $timeout = null): mixed` function can be used to
block waiting for the given `$promise` to be fulfilled.

```php
$result = Clue\React\Block\await($promise);
```

This function will only return after the given `$promise` has settled, i.e.
either fulfilled or rejected. In the meantime, the event loop will run any
events attached to the same loop until the promise settles.

Once the promise is fulfilled, this function will return whatever the promise
resolved to.

Once the promise is rejected, this will throw whatever the promise rejected
with. If the promise did not reject with an `Exception`, then this function
will throw an `UnexpectedValueException` instead.

```php
try {
    $result = Clue\React\Block\await($promise);
    // promise successfully fulfilled with $result
    echo 'Result: ' . $result;
} catch (Exception $exception) {
    // promise rejected with $exception
    echo 'ERROR: ' . $exception->getMessage();
}
```

See also the [examples](examples/).

This function takes an optional `LoopInterface|null $loop` parameter that can be used to
pass the event loop instance to use. You can use a `null` value here in order to
use the [default loop](https://github.com/reactphp/event-loop#loop). This value
SHOULD NOT be given unless you're sure you want to explicitly use a given event
loop instance.

If no `$timeout` argument is given and the promise stays pending, then this
will potentially wait/block forever until the promise is settled. To avoid
this, API authors creating promises are expected to provide means to
configure a timeout for the promise instead. For more details, see also the
[`timeout()` function](https://github.com/reactphp/promise-timer#timeout).

If the deprecated `$timeout` argument is given and the promise is still pending once the
timeout triggers, this will `cancel()` the promise and throw a `TimeoutException`.
This implies that if you pass a really small (or negative) value, it will still
start a timer and will thus trigger at the earliest possible time in the future.

Note that this function will assume control over the event loop. Internally, it
will actually `run()` the loop until the promise settles and then calls `stop()` to
terminate execution of the loop. This means this function is more suited for
short-lived promise executions when using promise-based APIs is not feasible.
For long-running applications, using promise-based APIs by leveraging chained
`then()` calls is usually preferable.

### awaitAny()

The `awaitAny(PromiseInterface[] $promises, ?LoopInterface $loop = null, ?float $timeout = null): mixed` function can be used to
wait for ANY of the given promises to be fulfilled.

```php
$promises = array(
    $promise1,
    $promise2
);

$firstResult = Clue\React\Block\awaitAny($promises);

echo 'First result: ' . $firstResult;
```

See also the [examples](examples/).

This function will only return after ANY of the given `$promises` has been
fulfilled or will throw when ALL of them have been rejected. In the meantime,
the event loop will run any events attached to the same loop.

Once ANY promise is fulfilled, this function will return whatever this
promise resolved to and will try to `cancel()` all remaining promises.

Once ALL promises reject, this function will fail and throw an `UnderflowException`.
Likewise, this will throw if an empty array of `$promises` is passed.

This function takes an optional `LoopInterface|null $loop` parameter that can be used to
pass the event loop instance to use. You can use a `null` value here in order to
use the [default loop](https://github.com/reactphp/event-loop#loop). This value
SHOULD NOT be given unless you're sure you want to explicitly use a given event
loop instance.

If no `$timeout` argument is given and ALL promises stay pending, then this
will potentially wait/block forever until the promise is fulfilled. To avoid
this, API authors creating promises are expected to provide means to
configure a timeout for the promise instead. For more details, see also the
[`timeout()` function](https://github.com/reactphp/promise-timer#timeout).

If the deprecated `$timeout` argument is given and ANY promises are still pending once
the timeout triggers, this will `cancel()` all pending promises and throw a
`TimeoutException`. This implies that if you pass a really small (or negative)
value, it will still start a timer and will thus trigger at the earliest
possible time in the future.

Note that this function will assume control over the event loop. Internally, it
will actually `run()` the loop until the promise settles and then calls `stop()` to
terminate execution of the loop. This means this function is more suited for
short-lived promise executions when using promise-based APIs is not feasible.
For long-running applications, using promise-based APIs by leveraging chained
`then()` calls is usually preferable.

### awaitAll()

The `awaitAll(PromiseInterface[] $promises, ?LoopInterface $loop = null, ?float $timeout = null): mixed[]` function can be used to
wait for ALL of the given promises to be fulfilled.

```php
$promises = array(
    $promise1,
    $promise2
);

$allResults = Clue\React\Block\awaitAll($promises);

echo 'First promise resolved with: ' . $allResults[0];
```

See also the [examples](examples/).

This function will only return after ALL of the given `$promises` have been
fulfilled or will throw when ANY of them have been rejected. In the meantime,
the event loop will run any events attached to the same loop.

Once ALL promises are fulfilled, this will return an array with whatever
each promise resolves to. Array keys will be left intact, i.e. they can
be used to correlate the return array to the promises passed.
Likewise, this will return an empty array if an empty array of `$promises` is passed.

Once ANY promise rejects, this will try to `cancel()` all remaining promises
and throw an `Exception`. If the promise did not reject with an `Exception`,
then this function will throw an `UnexpectedValueException` instead.

This function takes an optional `LoopInterface|null $loop` parameter that can be used to
pass the event loop instance to use. You can use a `null` value here in order to
use the [default loop](https://github.com/reactphp/event-loop#loop). This value
SHOULD NOT be given unless you're sure you want to explicitly use a given event
loop instance.

If no `$timeout` argument is given and ANY promises stay pending, then this
will potentially wait/block forever until the promise is fulfilled. To avoid
this, API authors creating promises are expected to provide means to
configure a timeout for the promise instead. For more details, see also the
[`timeout()` function](https://github.com/reactphp/promise-timer#timeout).

If the deprecated `$timeout` argument is given and ANY promises are still pending once
the timeout triggers, this will `cancel()` all pending promises and throw a
`TimeoutException`. This implies that if you pass a really small (or negative)
value, it will still start a timer and will thus trigger at the earliest
possible time in the future.

Note that this function will assume control over the event loop. Internally, it
will actually `run()` the loop until the promise settles and then calls `stop()` to
terminate execution of the loop. This means this function is more suited for
short-lived promise executions when using promise-based APIs is not feasible.
For long-running applications, using promise-based APIs by leveraging chained
`then()` calls is usually preferable.

## Install

The recommended way to install this library is [through Composer](https://getcomposer.org/).
[New to Composer?](https://getcomposer.org/doc/00-intro.md)

This project follows [SemVer](https://semver.org/).
This will install the latest supported version:

```bash
$ composer require clue/block-react:^1.5
```

See also the [CHANGELOG](CHANGELOG.md) for details about version upgrades.

This project aims to run on any platform and thus does not require any PHP
extensions and supports running on legacy PHP 5.3 through current PHP 8+ and
HHVM.
It's *highly recommended to use the latest supported PHP version* for this project.

## Tests

To run the test suite, you first need to clone this repo and then install all
dependencies [through Composer](https://getcomposer.org/):

```bash
$ composer install
```

To run the test suite, go to the project root and run:

```bash
$ vendor/bin/phpunit
```

## License

This project is released under the permissive [MIT license](LICENSE).

> Did you know that I offer custom development services and issuing invoices for
  sponsorships of releases and for contributions? Contact me (@clue) for details.
