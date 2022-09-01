<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace test\diff\compare_greater;

use strangetest;


function test_equal_arrays()
{
    $actual = strangetest\assert_throws(
        'strangetest\\Failure',
        function() {
            strangetest\assert_greater(array(1, '2', 3, 4), array(1, 2, 3, '4'));
        }
    );

    $expected = <<<'EXPECTED'
Assertion "$actual > $min" failed

-> $actual
+< $min

  array(
>     1,
>     '2',
>     3,
>     4,
<     1,
<     2,
<     3,
<     '4',
  )
EXPECTED;

    strangetest\assert_identical($actual->getMessage(), $expected);
}


function test_array_less_than_array()
{
    $actual = strangetest\assert_throws(
        'strangetest\\Failure',
        function() {
            $array1 = array(1, array(2, (object)array(1, 1, 3),   3,  2), 5, 5);
            $array2 = array(1, array(2, (object)array(1, 2, 3), 500, 10), 5, 2);
            strangetest\assert_greater($array1, $array2);
        }
    );

    $expected = <<<'EXPECTED'
Assertion "$actual > $min" failed

-> $actual
+< $min

  array(
>     1,
<     1,
      array(
>         2,
<         2,
          stdClass {
>             $0 = 1;
>             $1 = 1;
<             $0 = 1;
<             $1 = 2;
              $2 = 3;
          },
          3,
          2,
      ),
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
            strangetest\assert_greater(
                array(      3, 3, 5),
                array(1, 2, 3, 4, 5)
            );
        }
    );

    $expected = <<<'EXPECTED'
Assertion "$actual > $min" failed

-> $actual
+< $min

  array(
+     1,
+     2,
>     3,
>     3,
<     3,
<     4,
      5,
  )
EXPECTED;

    strangetest\assert_identical($actual->getMessage(), $expected);
}


function test_array_less_than_object()
{
    $actual = strangetest\assert_throws(
        'strangetest\\Failure',
        function() {
            strangetest\assert_greater(array(), new \stdClass);
        }
    );

    $expected = <<<'EXPECTED'
Assertion "$actual > $min" failed

-> $actual
+< $min

> array()
< stdClass {}
EXPECTED;

    strangetest\assert_identical($actual->getMessage(), $expected);
}


function test_object_less_than_object()
{
    $actual = strangetest\assert_throws(
        'strangetest\\Failure',
        function() {
            $object1 = (object)array(1, 1, 3);
            $object2 = (object)array(1, 2, 3);
            strangetest\assert_greater($object1, $object2);
        }
    );

    $expected = <<<'EXPECTED'
Assertion "$actual > $min" failed

-> $actual
+< $min

  stdClass {
>     $0 = 1;
>     $1 = 1;
<     $0 = 1;
<     $1 = 2;
      $2 = 3;
  }
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
            strangetest\assert_greater($string1, $string2);
        }
    );

    $expected = <<<'EXPECTED'
Assertion "$actual > $min" failed

-> $actual
+< $min

> 'One
> Three
< 'One
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
            strangetest\assert_greater($string1, $string2);
        }
    );

    $expected = <<<'EXPECTED'
Assertion "$actual > $min" failed

-> $actual
+< $min

> 'Two
> One
< 'Two
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
            strangetest\assert_greater($string1, $string2);
        }
    );

    $expected = <<<'EXPECTED'
Assertion "$actual > $min" failed

-> $actual
+< $min

> 'One
> Two
< 'One
< Two
  Three'
EXPECTED;

    strangetest\assert_identical($actual->getMessage(), $expected);
}
