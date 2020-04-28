<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
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
        throw new easytest\Failure(
            sprintf($msg, var_export($expected, true), var_export($actual, true))
        );
    }


    // tests

    public function test_passes() {
        easytest\assert_identical(1, 1);
    }


    public function test_shows_reason_for_failure() {
        // NOTE: Test of equal arrays in different key order to ensure 1) this
        // fails, and 2) they're not sorted when displayed
        try {
            $array1 = [
                1,
                [2, 3],
                [],
                4,
            ];
            $array2 = [
                3 => 4,
                2 => [],
                1 => [1 => 3, 0 => 2],
                0 => 1,
            ];
            easytest\assert_identical($array1, $array2);
        }
        catch (easytest\Failure $actual) {}

        if (!isset($actual)) {
            throw new easytest\Failure('Did not fail on non-identical arrays');
        }

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
        $this->assert_identical($expected, $actual->getMessage());
    }


    public function test_uses_provided_message() {
        $message = 'Fail! :-(';
        try {
            easytest\assert_identical(1, '1', $message);
        }
        catch (easytest\Failure $actual) {}

        if (!isset($actual)) {
            throw new easytest\Failure('Did not fail on non-identical values');
        }

        $expected = <<<EXPECTED
Assertion "\$expected === \$actual" failed
$message

- expected
+ actual

- 1
+ '1'
EXPECTED;
        $this->assert_identical($expected, $actual->getMessage());
    }
}



class TestAssertThrows {
    public function test_returns_expected_exception() {
        $expected = new ExpectedException();
        $actual = easytest\assert_throws(
            'ExpectedException',
            function() use ($expected) { throw $expected; }
        );
        easytest\assert_identical($expected, $actual);
    }


    public function test_fails_when_no_exception_is_thrown() {
        try {
            easytest\assert_throws('ExpectedException', function() {});
        }
        catch (easytest\Failure $actual) {}

        if (!isset($actual)) {
            throw new easytest\Failure('Did not fail when no exception was thrown');
        }

        easytest\assert_identical(
            'Expected to catch ExpectedException but no exception was thrown',
            $actual->getMessage()
        );
    }


    public function test_rethrows_unexpected_exception() {
        $expected = new UnexpectedException();
        try {
            easytest\assert_throws(
                'ExpectedException',
                function() use ($expected) { throw $expected; }
            );
        }
        catch (\Exception $actual) {}

        if (!isset($actual)) {
            throw new easytest\Failure('Did not rethrow an unexpected exception');
        }

        easytest\assert_identical(
            "Expected to catch ExpectedException but instead caught UnexpectedException",
            $actual->getMessage()
        );
        easytest\assert_identical($expected, $actual->getPrevious());
    }


    public function test_uses_provided_message() {
        $expected = 'My custom failure message.';
        try {
            easytest\assert_throws(
                'ExpectedException',
                function() {},
                $expected
            );
        }
        catch (easytest\Failure $actual) {}

        if (!isset($actual)) {
            throw new easytest\Failure('Did not fail when no exception was thrown');
        }

        easytest\assert_identical(
            "Expected to catch ExpectedException but no exception was thrown\n$expected",
            $actual->getMessage()
        );
    }
}



class TestAssertEqual {

    public function test_passes() {
        // NOTE: Test of equal arrays that are in different key order to
        // ensure this passes
        $array1 = [
            1,
            [2, 3],
            [],
            4,
        ];
        $array2 = [
            3 => 4,
            2 => [],
            1 => [1 => 3, 0 => 2],
            0 => 1,
        ];
        easytest\assert_equal($array1, $array2);
    }


    public function test_shows_reason_for_failure() {
        $actual = easytest\assert_throws(
            'easytest\\Failure',
            function() {
                // NOTE: Test of unequal arrays with elements in different key
                // order to ensure that they're sorted by key when displayed
                $array1 = [
                    1,
                    [2, 3],
                    [],
                    4,
                ];
                $array2 = [
                    3 => 5,
                    2 => [],
                    1 => [1 => 3, 0 => 2],
                    0 => 1,
                ];
                /* Ensure recursion is handled */
                $array1[] = &$array1;
                $array2[] = &$array2;

                easytest\assert_equal($array1, $array2);
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

        easytest\assert_identical($expected, $actual->getMessage());
    }


    public function test_uses_provided_message() {
        $message = 'Fail! :-(';
        $actual = easytest\assert_throws(
            'easytest\\Failure',
            function() use ($message) {
                easytest\assert_equal(true, false, $message);
            }
        );

        $expected = <<<EXPECTED
Assertion "\$expected == \$actual" failed
$message

- expected
+ actual

- true
+ false
EXPECTED;

        easytest\assert_identical($expected, $actual->getMessage());
    }
}
