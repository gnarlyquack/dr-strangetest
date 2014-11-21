<?php

class TestAssert {
    public function test_assert() {
        $e = easytest\assert_exception(
            'easytest\\Failure',
            function() { assert(true == false); }
        );

        $actual = $e->getMessage();
        assert('"Assertion failed" === $actual');
    }

    public function test_assert_with_code() {
        $e = easytest\assert_exception(
            'easytest\\Failure',
            function() { assert('true == false'); }
        );

        $expected = 'Assertion "true == false" failed';
        $actual = $e->getMessage();
        assert('$expected === $actual');
    }

    public function test_assert_with_description() {
        if (version_compare(PHP_VERSION, '5.4.8', '<')) {
            easytest\skip(
                "assert() description parameter wasn't added until PHP 5.4.8"
            );
        }

        $expected = 'My assertion failed. Or did it?';
        $e = easytest\assert_exception(
            'easytest\\Failure',
            function() use ($expected) { assert('true == false', $expected); }
        );

        $actual = $e->getMessage();
        assert('$expected === $actual');
    }

    /*
     * Two variables in the assertion context (which is expected to be the
     * most common case) should produce a diff-style output.
     */
    public function test_assert_with_diff() {
        $one = true;
        $two = false;
        $e = easytest\assert_exception(
            'easytest\\Failure',
            function() use ($one, $two) { assert('$one == $two'); }
        );

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
    public function test_assert_with_variables() {
        $one = 1;
        $two = 2;
        $four = 4;
        $e = easytest\assert_exception(
            'easytest\\Failure',
            function() use ($one, $two, $four) {
                assert('$one + $two == $four');
            }
        );

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

        $e = easytest\assert_exception(
            'easytest\\Failure',
            function() use ($expected, $actual) {
                assert('$expected == $actual');
            }
        );

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

        $e = easytest\assert_exception(
            'easytest\\Failure',
            function() use ($expected, $actual) {
                assert('$expected === $actual');
            }
        );

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


class TestAssertException {
    public function test_passes_when_exception_thrown() {
        $result = easytest\assert_exception(
            'Exception',
            function() { throw new \Exception(); }
        );
        assert('$result instanceof \\Exception');
    }

    public function test_fails_when_no_exception_thrown() {
        try {
            easytest\assert_exception('Exception', function() {});
        }
        catch (easytest\Failure $e) {}

        if (!isset($e)) {
            throw new easytest\Failure(
                'assert_exception() did not fail when no exception was thrown'
            );
        }

        $expected = 'No exception was thrown although one was expected';
        $actual = $e->getMessage();
        assert('$expected === $actual');
    }

    public function test_rethrows_unexpected_exception() {
        try {
            easytest\assert_exception(
                'easytest\\Failure',
                function() { throw new easytest\Skip(); }
            );
            throw new easytest\Failure(
                'assert_exception() did not rethrow an unexpected exception'
            );
        }
        catch (easytest\Skip $e) {}
    }

    public function test_failure_message() {
        $expected = 'My custom failure message.';
        try {
            easytest\assert_exception('Exception', function() {}, $expected);
        }
        catch (easytest\Failure $e) {
            $actual = $e->getMessage();
            assert('$expected === $actual');
        }
    }
}


class TestAssertEqual {
    public function test_passes() {
        easytest\assert_equal(1, '1');
    }

    public function test_fails() {
        $e = easytest\assert_exception(
            'easytest\\Failure',
            function() { easytest\assert_equal(true, false); }
        );

        $expected = <<<'EXPECTED'
Assertion "$expected == $actual" failed

- expected
+ actual

- true
+ false
EXPECTED;
        $actual = $e->getMessage();
        assert('$expected === $actual');
    }

    public function test_message() {
        $e = easytest\assert_exception(
            'easytest\\Failure',
            function() { easytest\assert_equal(true, false, 'My message'); }
        );

        $expected = <<<'EXPECTED'
My message

- expected
+ actual

- true
+ false
EXPECTED;
        $actual = $e->getMessage();
        assert('$expected === $actual');
    }
}


class TestAssertIdentical {
    public function test_passes() {
        easytest\assert_identical(1, 1);
    }

    public function test_fails() {
        $e = easytest\assert_exception(
            'easytest\\Failure',
            function() { easytest\assert_identical(1, '1'); }
        );

        $expected = <<<'EXPECTED'
Assertion "$expected === $actual" failed

- expected
+ actual

- 1
+ '1'
EXPECTED;
        $actual = $e->getMessage();
        assert('$expected === $actual');
    }

    public function test_message() {
        $e = easytest\assert_exception(
            'easytest\\Failure',
            function() { easytest\assert_identical(1, '1', 'My message'); }
        );

        $expected = <<<'EXPECTED'
My message

- expected
+ actual

- 1
+ '1'
EXPECTED;
        $actual = $e->getMessage();
        assert('$expected === $actual');
    }
}


class TestSkip {
    public function test_skip() {
        $expected = 'Skip me';
        $e = easytest\assert_exception(
            'easytest\\Skip',
            function() use ($expected) { easytest\skip($expected); }
        );

        $actual = $e->getMessage();
        assert('$expected === $actual');
    }
}
