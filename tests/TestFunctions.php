<?php

class TestAssert {
    public function test_failed_assertion() {
        try {
            assert(true == false);
            throw new \Exception("Failed assertion didn't trigger an exception");
        }
        catch (easytest\Failure $e) {}

        $actual = $e->getMessage();
        assert('"Assertion failed" === $actual');
    }

    public function test_failed_assertion_with_code() {
        try {
            assert('true == false');
            throw new \Exception("Failed assertion didn't trigger an exception");
        }
        catch (easytest\Failure $e) {}

        $expected = 'Assertion "true == false" failed';
        $actual = $e->getMessage();
        assert('$expected === $actual');
    }

    public function test_failed_assertion_with_message() {
        if (version_compare(PHP_VERSION, '5.4.8') < 0) {
            easytest\skip(
                "assert() description parameter wasn't added until PHP 5.4.8"
            );
        }

        $expected = 'My assertion failed. Or did it?';
        try {
            assert('true == false', $expected);
            throw new \Exception("Failed assertion didn't trigger an exception");
        }
        catch (easytest\Failure $e) {}

        $actual = $e->getMessage();
        assert('$expected === $actual');
    }

    /*
     * Two variables in the assertion context (which is expected to be the
     * most common case) should produce a diff-style output.
     */
    public function test_failed_assertion_with_diff() {
        $one = true;
        $two = false;
        try {
            assert('$one == $two');
            throw new \Exception("Failed assertion didn't trigger an exception");
        }
        catch (easytest\Failure $e) {}

        $expected = <<<'EXPECTED'
Assertion "$one == $two" failed

- one
+ two

- true
+ false
EXPECTED;
        $actual = $e->getMessage();
        assert('$expected === $actual');
    }

    /*
     * Having more (or less) than two variables in the assertion context
     * should simply output the value of each variable.
     */
    public function test_failed_assertion_with_variables() {
        $one = 1;
        $two = 2;
        $four = 4;
        try {
            assert('$one + $two == $four');
            throw new \Exception("Failed assertion didn't trigger an exception");
        }
        catch (easytest\Failure $e) {}

        $expected = <<<'EXPECTED'
Assertion "$one + $two == $four" failed

one:
1

two:
2

four:
4
EXPECTED;
        $actual = $e->getMessage();
        assert('$expected === $actual');
    }

    public function test_unequal_arrays_are_sorted() {
        $expected = [
            1,
            [2, 3],
            [],
            4,
        ];
        $actual = [
            3 => 5,
            2 => [],
            1 => [1 => 3, 0 => 2],
            0 => 1,
        ];
        /* Ensure recursion is handled */
        $expected[] = &$expected;
        $actual[] = &$actual;

        try {
            assert('$expected == $actual');
            throw new \Exception("Failed assertion didn't trigger an exception");
        }
        catch (easytest\Failure $e) {}

        $expected = <<<'EXPECTED'
Assertion "$expected == $actual" failed

- expected
+ actual

  array(
      0 => 1,
      1 => array(
          0 => 2,
          1 => 3,
      ),
      2 => array(),
-     3 => 4,
+     3 => 5,
      4 => array(
          0 => 1,
          1 => array(
              0 => 2,
              1 => 3,
          ),
          2 => array(),
-         3 => 4,
+         3 => 5,
          4 => &array[4],
      ),
  )
EXPECTED;
        $actual = $e->getMessage();
        assert('$expected === $actual');
    }

    public function test_nonidentical_arrays_are_not_sorted() {
        $expected = [
            1,
            [2, 3],
            [],
            4,
        ];
        $actual = [
            3 => 4,
            2 => [],
            1 => [1 => 3, 0 => 2],
            0 => 1,
        ];

        try {
            assert('$expected === $actual');
            throw new \Exception("Failed assertion didn't trigger an exception");
        }
        catch (easytest\Failure $e) {}

        $expected = <<<'EXPECTED'
Assertion "$expected === $actual" failed

- expected
+ actual

  array(
-     0 => 1,
+     3 => 4,
+     2 => array(),
      1 => array(
-         0 => 2,
          1 => 3,
+         0 => 2,
      ),
-     2 => array(),
-     3 => 4,
+     0 => 1,
  )
EXPECTED;
        $actual = $e->getMessage();
        assert('$expected === $actual');
    }
}


class TestSkip {
    public function test_skip() {
        $expected = 'Skip me';
        try {
            easytest\skip($expected);
            throw new \Exception("skip() didn't cause a skip");
        }
        catch (easytest\Skip $e) {}

        $actual = $e->getMessage();
        assert('$expected === $actual');
    }
}
