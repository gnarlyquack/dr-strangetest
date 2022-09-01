<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace test\diff\compare_greater_equal;

use strangetest;


function test_array_less_than_array()
{
    $actual = strangetest\assert_throws(
        'strangetest\\Failure',
        function() {
            strangetest\assert_greater_or_equal(
                array(1,   3,  2, 5, 5),
                array(1, 500, 10, 5, 2));
                }
    );

    $expected = <<<'EXPECTED'
Assertion "$actual >= $min" failed

 > $actual
+< $min

  array(
      1,
>     3,
<     500,
      2,
      5,
      5,
  )
EXPECTED;

    strangetest\assert_identical($actual->getMessage(), $expected);
}


function test_shorter_array_less_than_longer_array()
{
    $actual = strangetest\assert_throws(
        'strangetest\\Failure',
        function() {
            strangetest\assert_greater_or_equal(
                array(      3, 4),
                array(1, 2, 3, 4)
            );
        }
    );

    $expected = <<<'EXPECTED'
Assertion "$actual >= $min" failed

 > $actual
+< $min

  array(
+     1,
+     2,
      3,
      4,
  )
EXPECTED;

    strangetest\assert_identical($actual->getMessage(), $expected);
}


function test_array_less_than_object()
{
    $actual = strangetest\assert_throws(
        'strangetest\\Failure',
        function() {
            strangetest\assert_greater_or_equal(array(), new \stdClass);
        }
    );

    $expected = <<<'EXPECTED'
Assertion "$actual >= $min" failed

 > $actual
+< $min

> array()
< stdClass {}
EXPECTED;

    strangetest\assert_identical($actual->getMessage(), $expected);
}


function test_longer_string_less_than_shorter_string()
{
    $actual = strangetest\assert_throws(
        'strangetest\\Failure',
        function() {
            $string1 = "One\nThree\nFive";
            $string2 = "One\nTwo";
            strangetest\assert_greater_or_equal($string1, $string2);
        }
    );

    $expected = <<<'EXPECTED'
Assertion "$actual >= $min" failed

 > $actual
+< $min

  'One
> Three
< Two
  Five'
EXPECTED;

    strangetest\assert_identical($actual->getMessage(), $expected);
}


function test_shorter_string_less_than_longer_string()
{
    $actual = strangetest\assert_throws(
        'strangetest\\Failure',
        function() {
            $string1 = "Two\nOne";
            $string2 = "Two\nThree\nFour";
            strangetest\assert_greater_or_equal($string1, $string2);
        }
    );

    $expected = <<<'EXPECTED'
Assertion "$actual >= $min" failed

 > $actual
+< $min

  'Two
> One
< Three
  Four'
EXPECTED;

    strangetest\assert_identical($actual->getMessage(), $expected);
}


function test_common_shorter_string_less_than_longer_string()
{
    $actual = strangetest\assert_throws(
        'strangetest\\Failure',
        function() {
            $string1 = "One\nTwo";
            $string2 = "One\nTwo\nThree";
            strangetest\assert_greater_or_equal($string1, $string2);
        }
    );

    $expected = <<<'EXPECTED'
Assertion "$actual >= $min" failed

 > $actual
+< $min

  'One
  Two
+ Three'
EXPECTED;

    strangetest\assert_identical($actual->getMessage(), $expected);
}
