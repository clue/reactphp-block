# Changelog

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
