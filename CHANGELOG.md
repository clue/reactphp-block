# Changelog

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
