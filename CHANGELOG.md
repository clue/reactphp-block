# Changelog

## 1.3.1 (2019-04-09)

*   Fix: Fix getting the type of unexpected rejection reason when not rejecting with an `Exception`.
    (#42 by @Furgas and @clue)

*   Fix: Check if the function is declared before declaring it.
    (#39 by @Niko9911)

## 1.3.0 (2018-06-14)

*   Feature: Improve memory consumption by cleaning up garbage references.
    (#35 by @clue)

*   Fix minor documentation typos.
    (#28 by @seregazhuk)

*   Improve test suite by locking Travis distro so new defaults will not break the build,
    support PHPUnit 6 and update Travis config to also test against PHP 7.2.
    (#30 by @clue, #31 by @carusogabriel and #32 by @andreybolonin)

*   Update project homepage.
    (#34 by @clue)

## 1.2.0 (2017-08-03)

* Feature / Fix: Forward compatibility with future EventLoop v1.0 and v0.5 and
  cap small timeout values for legacy EventLoop
  (#26 by @clue)

  ```php
  // now works across all versions
  Block\sleep(0.000001, $loop);
  ```

* Feature / Fix: Throw `UnexpectedValueException` if Promise gets rejected with non-Exception
  (#27 by @clue)

  ```php
  // now throws an UnexceptedValueException
  Block\await(Promise\reject(false), $loop);
  ```

* First class support for legacy PHP 5.3 through PHP 7.1 and HHVM
  (#24 and #25 by @clue)

* Improve testsuite by adding PHPUnit to require-dev and
  Fix HHVM build for now again and ignore future HHVM build errors
  (#23 and #24 by @clue)

## 1.1.0 (2016-03-09)

* Feature: Add optional timeout parameter to all await*() functions
  (#17 by @clue)

* Feature: Cancellation is now supported across all PHP versions
  (#16 by @clue)

## 1.0.0 (2015-11-13)

* First stable release, now following SemVer
* Improved documentation

> Contains no other changes, so it's actually fully compatible with the v0.3.0 release.

## 0.3.0 (2015-07-09)

* BC break: Use functional API approach instead of pseudo-OOP.
  All existing methods are now exposed as simple functions.
  ([#13](https://github.com/clue/php-block-react/pull/13))
  ```php
// old
$blocker = new Block\Blocker($loop);
$result = $blocker->await($promise);

// new
$result = Block\await($promise, $loop);
```

## 0.2.0 (2015-07-05)

* BC break: Rename methods in order to avoid confusion.
  * Rename `wait()` to `sleep()`.
    ([#8](https://github.com/clue/php-block-react/pull/8))
  * Rename `awaitRace()` to `awaitAny()`.
    ([#9](https://github.com/clue/php-block-react/pull/9))
  * Rename `awaitOne()` to `await()`.
    ([#10](https://github.com/clue/php-block-react/pull/10))

## 0.1.1 (2015-04-05)

* `run()` the loop instead of making it `tick()`.
  This results in significant performance improvements (less resource utilization) by avoiding busy waiting
  ([#1](https://github.com/clue/php-block-react/pull/1))

## 0.1.0 (2015-04-04)

* First tagged release
