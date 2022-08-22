<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

class TestAssertIdentical {
    // helper assertions
    private function assert_identical($expected, $actual) {
        if ($expected === $actual) {
            return;
        }

        $msg = <<<'MSG'
Assertion $expected === $actual failed

$expected:
%s

$actual:
%s
MSG;
        throw new strangetest\Failure(
            sprintf($msg, var_export($expected, true), var_export($actual, true))
        );
    }


    // tests

    public function test_passes() {
        strangetest\assert_identical(1, 1);
    }


    public function test_shows_reason_for_failure() {
        // NOTE: Test of equal arrays in different key order to ensure 1) this
        // fails, and 2) they're not sorted when displayed
        try {
            $array1 = array(
                1,
                array(2, 3),
                array(),
                4,
            );
            $array2 = array(
                3 => 4,
                2 => array(),
                1 => array(1 => 3, 0 => 2),
                0 => 1,
            );
            strangetest\assert_identical($array1, $array2);
        }
        catch (strangetest\Failure $actual) {}

        if (!isset($actual)) {
            throw new strangetest\Failure('Did not fail on non-identical arrays');
        }

        $expected = <<<'EXPECTED'
Assertion "$actual === $expected" failed

- $actual
+ $expected

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
        $this->assert_identical($expected, $actual->getMessage());
    }


    public function test_uses_provided_message() {
        $message = 'Fail! :-(';
        try {
            strangetest\assert_identical(1, '1', $message);
        }
        catch (strangetest\Failure $actual) {}

        if (!isset($actual)) {
            throw new strangetest\Failure('Did not fail on non-identical values');
        }

        $expected = <<<EXPECTED
Assertion "\$actual === \$expected" failed
$message

- \$actual
+ \$expected

- 1
+ '1'
EXPECTED;
        $this->assert_identical($expected, $actual->getMessage());
    }
}



class TestAssertThrows {
    public function test_returns_expected_exception() {
        $expected = new ExpectedException();
        $actual = strangetest\assert_throws(
            'ExpectedException',
            function() use ($expected) { throw $expected; }
        );
        strangetest\assert_identical($expected, $actual);
    }


    public function test_fails_when_no_exception_is_thrown() {
        try {
            strangetest\assert_throws('ExpectedException', function() {});
        }
        catch (strangetest\Failure $actual) {}

        if (!isset($actual)) {
            throw new strangetest\Failure('Did not fail when no exception was thrown');
        }

        strangetest\assert_identical(
            'Expected to catch ExpectedException but no exception was thrown',
            $actual->getMessage()
        );
    }


    public function test_rethrows_unexpected_exception() {
        $expected = new UnexpectedException();
        try {
            strangetest\assert_throws(
                'ExpectedException',
                function() use ($expected) { throw $expected; }
            );
        }
        catch (\Exception $actual) {}

        if (!isset($actual)) {
            throw new strangetest\Failure('Did not rethrow an unexpected exception');
        }

        strangetest\assert_identical(
            "Expected to catch ExpectedException but instead caught UnexpectedException",
            $actual->getMessage()
        );
        strangetest\assert_identical($expected, $actual->getPrevious());
    }


    public function test_uses_provided_message() {
        $expected = 'My custom failure message.';
        try {
            strangetest\assert_throws(
                'ExpectedException',
                function() {},
                $expected
            );
        }
        catch (strangetest\Failure $actual) {}

        if (!isset($actual)) {
            throw new strangetest\Failure('Did not fail when no exception was thrown');
        }

        strangetest\assert_identical(
            "Expected to catch ExpectedException but no exception was thrown\n$expected",
            $actual->getMessage()
        );
    }
}



class TestAssertEqual {
    public function test_passes() {
        // NOTE: Test of equal arrays that are in different key order to
        // ensure this passes
        $array1 = array(
            1,
            array(2, 3),
            array(),
            4,
        );
        $array2 = array(
            3 => 4,
            2 => array(),
            1 => array(1 => 3, 0 => 2),
            0 => 1,
        );
        strangetest\assert_equal($array1, $array2);
    }


    public function test_shows_reason_for_failure() {
        $actual = strangetest\assert_throws(
            'strangetest\\Failure',
            function() {
                // NOTE: Test of unequal arrays with elements in different key
                // order to ensure that they're sorted by key when displayed
                $array1 = array(
                    1,
                    array(2, 3),
                    array(),
                    4,
                );
                $array2 = array(
                    3 => 5,
                    2 => array(),
                    1 => array(1 => 3, 0 => 2),
                    0 => 1,
                );
                /* Ensure recursion is handled */
                $array1[] = &$array1;
                $array2[] = &$array2;

                strangetest\assert_equal($array1, $array2);
            }
        );

        $expected = <<<'EXPECTED'
Assertion "$actual == $expected" failed

- $actual
+ $expected

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
-         4 => &$actual[4],
+         3 => 5,
+         4 => &$expected[4],
      ),
  )
EXPECTED;

        strangetest\assert_identical($expected, $actual->getMessage());
    }


    public function test_uses_provided_message() {
        $message = 'Fail! :-(';
        $actual = strangetest\assert_throws(
            'strangetest\\Failure',
            function() use ($message) {
                strangetest\assert_equal(true, false, $message);
            }
        );

        $expected = <<<EXPECTED
Assertion "\$actual == \$expected" failed
$message

- \$actual
+ \$expected

- true
+ false
EXPECTED;

        strangetest\assert_identical($expected, $actual->getMessage());
    }
}



class TestAssertDifferent {
    public function test_passes() {
        strangetest\assert_different('1', 1);
    }


    public function test_shows_reason_for_failure() {
        $actual = strangetest\assert_throws(
            'strangetest\\Failure',
            function() {
                strangetest\assert_different(1, 1);
            }
        );

        $expected = <<<'EXPECTED'
Assertion "$actual !== $expected" failed

$actual = $expected = 1
EXPECTED;

        strangetest\assert_identical($expected, $actual->getMessage());
    }


    public function test_shows_description() {
        $actual = strangetest\assert_throws(
            'strangetest\\Failure',
            function() {
                strangetest\assert_different(true, true, 'I failed.');
            }
        );

        $expected = <<<'EXPECTED'
Assertion "$actual !== $expected" failed
I failed.

$actual = $expected = true
EXPECTED;

        strangetest\assert_identical($expected, $actual->getMessage());
    }
}



class TestAssertFalse {
    public function test_passes() {
        strangetest\assert_false(false);
    }


    public function test_shows_reason_for_failure() {
        $actual = strangetest\assert_throws(
            'strangetest\\Failure',
            function() {
                strangetest\assert_false(null);
            }
        );

        $expected = <<<'EXPECTED'
Assertion "$actual === false" failed

$actual = NULL
EXPECTED;

        strangetest\assert_identical($expected, $actual->getMessage());
    }


    public function test_shows_description() {
        $actual = strangetest\assert_throws(
            'strangetest\\Failure',
            function() {
                strangetest\assert_false('0', 'I failed.');
            }
        );

        $expected = <<<'EXPECTED'
Assertion "$actual === false" failed
I failed.

$actual = '0'
EXPECTED;

        strangetest\assert_identical($expected, $actual->getMessage());
    }
}



class TestAssertFalsy {
    public function test_passes() {
        strangetest\assert_falsy(null);
    }


    public function test_shows_reason_for_failure() {
        $actual = strangetest\assert_throws(
            'strangetest\\Failure',
            function() {
                strangetest\assert_falsy(true);
            }
        );

        $expected = <<<'EXPECTED'
Assertion "$actual == false" failed

$actual = true
EXPECTED;

        strangetest\assert_identical($expected, $actual->getMessage());
    }


    public function test_shows_description() {
        $actual = strangetest\assert_throws(
            'strangetest\\Failure',
            function() {
                strangetest\assert_falsy('0 cabbage', 'I failed.');
            }
        );

        $expected = <<<'EXPECTED'
Assertion "$actual == false" failed
I failed.

$actual = '0 cabbage'
EXPECTED;

        strangetest\assert_identical($expected, $actual->getMessage());
    }
}



class TestAssertGreater {
    public function test_passes() {
        strangetest\assert_greater('3 bones', 0);
    }


    public function test_shows_reason_for_failure() {
        $actual = strangetest\assert_throws(
            'strangetest\\Failure',
            function() {
                strangetest\assert_greater(0, 0);
            }
        );

        $expected = <<<'EXPECTED'
Assertion "$actual > $min" failed

- $actual
+ $min

- 0
+ 0
EXPECTED;

        strangetest\assert_identical($expected, $actual->getMessage());
    }


    public function test_shows_description() {
        $actual = strangetest\assert_throws(
            'strangetest\\Failure',
            function() {
                strangetest\assert_greater(-6, -5, 'I failed.');
            }
        );

        $expected = <<<'EXPECTED'
Assertion "$actual > $min" failed
I failed.

- $actual
+ $min

- -6
+ -5
EXPECTED;

        strangetest\assert_identical($expected, $actual->getMessage());
    }

    public function test_equal_arrays()
    {
        $actual = strangetest\assert_throws(
            'strangetest\\Failure',
            function() {
                strangetest\assert_greater(array(1, '2', 3, 4), array(1, 2, 3, '4'));
            }
        );

        $expected = <<<'EXPECTED'
Assertion "$actual > $min" failed

- $actual
+ $min

  array(
-     0 => 1,
-     1 => '2',
-     2 => 3,
-     3 => 4,
+     0 => 1,
+     1 => 2,
+     2 => 3,
+     3 => '4',
  )
EXPECTED;

        strangetest\assert_identical($expected, $actual->getMessage());
    }

    public function test_array_less_than_array()
    {
        $actual = strangetest\assert_throws(
            'strangetest\\Failure',
            function() {
                strangetest\assert_greater(
                    array(1,   3, 5,  2, 5),
                    array(1, 500, 5, 10, 2));
            }
        );

        $expected = <<<'EXPECTED'
Assertion "$actual > $min" failed

- $actual
+ $min

  array(
-     0 => 1,
-     1 => 3,
-     2 => 5,
-     3 => 2,
+     0 => 1,
+     1 => 500,
+     2 => 5,
+     3 => 10,
      4 => 5,
  )
EXPECTED;

        strangetest\assert_identical($expected, $actual->getMessage());
    }

    public function test_array_greater_than_array()
    {
        strangetest\assert_greater(
            array(1, 500, 5,  2, 5),
            array(1,   3, 5, 10, 2));
    }

    public function test_object_vs_array()
    {
        strangetest\assert_greater(new \stdClass, array());
    }

    public function test_array_vs_object()
    {
        $actual = strangetest\assert_throws(
            'strangetest\\Failure',
            function() {
                strangetest\assert_greater(array(), new \stdClass);
            }
        );

        $expected = <<<'EXPECTED'
Assertion "$actual > $min" failed

- $actual
+ $min

- array()
+ stdClass {}
EXPECTED;

        strangetest\assert_identical($expected, $actual->getMessage());
    }
}



class TestAssertGreaterOrEqual {
    public function test_passes() {
        strangetest\assert_greater_or_equal('0 cabbage', 0);
    }


    public function test_shows_reason_for_failure() {
        $actual = strangetest\assert_throws(
            'strangetest\\Failure',
            function() {
                strangetest\assert_greater_or_equal(-1, 0);
            }
        );

        $expected = <<<'EXPECTED'
Assertion "$actual >= $min" failed

- $actual
+ $min

- -1
+ 0
EXPECTED;

        strangetest\assert_identical($expected, $actual->getMessage());
    }


    public function test_shows_description() {
        $actual = strangetest\assert_throws(
            'strangetest\\Failure',
            function() {
                strangetest\assert_greater_or_equal(-6, -5, 'I failed.');
            }
        );

        $expected = <<<'EXPECTED'
Assertion "$actual >= $min" failed
I failed.

- $actual
+ $min

- -6
+ -5
EXPECTED;

        strangetest\assert_identical($expected, $actual->getMessage());
    }

    public function test_equal_arrays()
    {
        strangetest\assert_greater_or_equal(array(1, '2', 3, 4), array(1, 2, 3, '4'));
    }

    public function test_array_less_than_array()
    {
        $actual = strangetest\assert_throws(
            'strangetest\\Failure',
            function() {
                strangetest\assert_greater_or_equal(
                    array(1,   3, 5,  2, 5),
                    array(1, 500, 5, 10, 2));
                    }
        );

        $expected = <<<'EXPECTED'
Assertion "$actual >= $min" failed

- $actual
+ $min

  array(
      0 => 1,
-     1 => 3,
+     1 => 500,
      2 => 5,
-     3 => 2,
+     3 => 10,
      4 => 5,
  )
EXPECTED;

        strangetest\assert_identical($expected, $actual->getMessage());
    }

    public function test_array_greater_than_array()
    {
        strangetest\assert_greater_or_equal(
            array(1, 500, 5,  2, 5),
            array(1,   3, 5, 10, 2));
    }

    public function test_object_vs_array()
    {
        strangetest\assert_greater_or_equal(new \stdClass, array());
    }

    public function test_array_vs_object()
    {
        $actual = strangetest\assert_throws(
            'strangetest\\Failure',
            function() {
                strangetest\assert_greater_or_equal(array(), new \stdClass);
            }
        );

        $expected = <<<'EXPECTED'
Assertion "$actual >= $min" failed

- $actual
+ $min

- array()
+ stdClass {}
EXPECTED;

        strangetest\assert_identical($expected, $actual->getMessage());
    }
}



class TestAssertLess {
    public function test_passes() {
        strangetest\assert_less('-3 bones', 0);
    }


    public function test_shows_reason_for_failure() {
        $actual = strangetest\assert_throws(
            'strangetest\\Failure',
            function() {
                strangetest\assert_less(0, 0);
            }
        );

        $expected = <<<'EXPECTED'
Assertion "$actual < $max" failed

- $actual
+ $max

- 0
+ 0
EXPECTED;

        strangetest\assert_identical($expected, $actual->getMessage());
    }


    public function test_shows_description() {
        $actual = strangetest\assert_throws(
            'strangetest\\Failure',
            function() {
                strangetest\assert_less(-5, -6, 'I failed.');
            }
        );

        $expected = <<<'EXPECTED'
Assertion "$actual < $max" failed
I failed.

- $actual
+ $max

- -5
+ -6
EXPECTED;

        strangetest\assert_identical($expected, $actual->getMessage());
    }

    public function test_equal_arrays()
    {
        $actual = strangetest\assert_throws(
            'strangetest\\Failure',
            function() {
                strangetest\assert_less(array(1, '2', 3, 4), array(1, 2, 3, '4'));
            }
        );

        $expected = <<<'EXPECTED'
Assertion "$actual < $max" failed

- $actual
+ $max

  array(
-     0 => 1,
-     1 => '2',
-     2 => 3,
-     3 => 4,
+     0 => 1,
+     1 => 2,
+     2 => 3,
+     3 => '4',
  )
EXPECTED;

        strangetest\assert_identical($expected, $actual->getMessage());
    }

    public function test_array_greater_than_array()
    {
        $actual = strangetest\assert_throws(
            'strangetest\\Failure',
            function() {
                strangetest\assert_less(
                    array(1, 500, 5,  2, 5),
                    array(1,   3, 5, 10, 2));
            }
        );

        $expected = <<<'EXPECTED'
Assertion "$actual < $max" failed

- $actual
+ $max

  array(
-     0 => 1,
-     1 => 500,
-     2 => 5,
+     0 => 1,
+     1 => 3,
+     2 => 5,
      3 => 2,
-     4 => 5,
+     4 => 2,
  )
EXPECTED;

        strangetest\assert_identical($expected, $actual->getMessage());
    }

    public function test_array_less_than_array()
    {
        strangetest\assert_less(
            array(1,   3, 5,  2, 5),
            array(1, 500, 5, 10, 2));
    }

    public function test_object_vs_array()
    {
        $actual = strangetest\assert_throws(
            'strangetest\\Failure',
            function() {
                strangetest\assert_less(new \stdClass, array());
            }
        );

        $expected = <<<'EXPECTED'
Assertion "$actual < $max" failed

- $actual
+ $max

- stdClass {}
+ array()
EXPECTED;

        strangetest\assert_identical($expected, $actual->getMessage());
    }

    public function test_array_vs_object()
    {
        strangetest\assert_less(array(), new \stdClass);
    }
}



class TestAssertLessOrEqual {
    public function test_passes() {
        strangetest\assert_less_or_equal('0', 0);
    }


    public function test_shows_reason_for_failure() {
        $actual = strangetest\assert_throws(
            'strangetest\\Failure',
            function() {
                strangetest\assert_less_or_equal(1, 0);
            }
        );

        $expected = <<<'EXPECTED'
Assertion "$actual <= $max" failed

- $actual
+ $max

- 1
+ 0
EXPECTED;

        strangetest\assert_identical($expected, $actual->getMessage());
    }


    public function test_shows_description() {
        $actual = strangetest\assert_throws(
            'strangetest\\Failure',
            function() {
                strangetest\assert_less_or_equal(-5, -6, 'I failed.');
            }
        );

        $expected = <<<'EXPECTED'
Assertion "$actual <= $max" failed
I failed.

- $actual
+ $max

- -5
+ -6
EXPECTED;

        strangetest\assert_identical($expected, $actual->getMessage());
    }


    public function test_equal_arrays()
    {
        strangetest\assert_less_or_equal(array(1, '2', 3, 4), array(1, 2, 3, '4'));
    }

    public function test_array_greater_than_array()
    {
        $actual = strangetest\assert_throws(
            'strangetest\\Failure',
            function() {
                strangetest\assert_less_or_equal(
                    array(1, 500, 5,  2, 5),
                    array(1,   3, 5, 10, 2));
            }
        );

        $expected = <<<'EXPECTED'
Assertion "$actual <= $max" failed

- $actual
+ $max

  array(
      0 => 1,
-     1 => 500,
+     1 => 3,
      2 => 5,
      3 => 2,
-     4 => 5,
+     4 => 2,
  )
EXPECTED;

        strangetest\assert_identical($expected, $actual->getMessage());
    }

    public function test_array_less_than_array()
    {
        strangetest\assert_less_or_equal(
            array(1,   3, 5,  2, 5),
            array(1, 500, 5, 10, 2));
    }

    public function test_object_vs_array()
    {
        $actual = strangetest\assert_throws(
            'strangetest\\Failure',
            function() {
                strangetest\assert_less_or_equal(new \stdClass, array());
            }
        );

        $expected = <<<'EXPECTED'
Assertion "$actual <= $max" failed

- $actual
+ $max

- stdClass {}
+ array()
EXPECTED;

        strangetest\assert_identical($expected, $actual->getMessage());
    }

    public function test_array_vs_object()
    {
        strangetest\assert_less_or_equal(array(), new \stdClass);
    }
}



class TestAssertTrue {
    public function test_passes() {
        strangetest\assert_true(true);
    }


    public function test_shows_reason_for_failure() {
        $actual = strangetest\assert_throws(
            'strangetest\\Failure',
            function() {
                strangetest\assert_true(1);
            }
        );

        $expected = <<<'EXPECTED'
Assertion "$actual === true" failed

$actual = 1
EXPECTED;

        strangetest\assert_identical($expected, $actual->getMessage());
    }


    public function test_shows_description() {
        $actual = strangetest\assert_throws(
            'strangetest\\Failure',
            function() {
                strangetest\assert_true('true', 'I failed.');
            }
        );

        $expected = <<<'EXPECTED'
Assertion "$actual === true" failed
I failed.

$actual = 'true'
EXPECTED;

        strangetest\assert_identical($expected, $actual->getMessage());
    }
}



class TestAssertTruthy {
    public function test_passes() {
        strangetest\assert_truthy(new \stdClass());
    }


    public function test_shows_reason_for_failure() {
        $actual = strangetest\assert_throws(
            'strangetest\\Failure',
            function() {
                strangetest\assert_truthy('0');
            }
        );

        $expected = <<<'EXPECTED'
Assertion "$actual == true" failed

$actual = '0'
EXPECTED;

        strangetest\assert_identical($expected, $actual->getMessage());
    }


    public function test_shows_description() {
        $actual = strangetest\assert_throws(
            'strangetest\\Failure',
            function() {
                strangetest\assert_truthy(array(), 'I failed.');
            }
        );

        $expected = <<<'EXPECTED'
Assertion "$actual == true" failed
I failed.

$actual = array()
EXPECTED;

        strangetest\assert_identical($expected, $actual->getMessage());
    }
}



class TestAssertUnequal {
    public function test_passes() {
        strangetest\assert_unequal('0', '');
    }


    public function test_shows_reason_for_failure() {
        $actual = strangetest\assert_throws(
            'strangetest\\Failure',
            function() {
                // NOTE: Test of equal arrays in different key order to ensure
                // they're sorted by key when displayed
                $array1 = array(
                    1,
                    array(2, 3),
                    array(),
                    4,
                );
                $array2 = array(
                    3 => 4,
                    2 => array(),
                    1 => array(1 => 3, 0 => 2),
                    0 => 1,
                );
                strangetest\assert_unequal($array1, $array2);
            }
        );

        $expected = <<<'EXPECTED'
Assertion "$actual != $expected" failed

- $actual
+ $expected

  array(
      0 => 1,
      1 => array(
          0 => 2,
          1 => 3,
      ),
      2 => array(),
      3 => 4,
  )
EXPECTED;

        strangetest\assert_identical($expected, $actual->getMessage());
    }


    public function test_shows_description() {
        $actual = strangetest\assert_throws(
            'strangetest\\Failure',
            function() {
                strangetest\assert_unequal(null, false, 'I failed.');
            }
        );

        $expected = <<<'EXPECTED'
Assertion "$actual != $expected" failed
I failed.

- $actual
+ $expected

- NULL
+ false
EXPECTED;

        strangetest\assert_identical($expected, $actual->getMessage());
    }
}
