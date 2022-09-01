<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace test\diff\compare_less;

use strangetest;


function test_equal_arrays()
{
    $actual = strangetest\assert_throws(
        'strangetest\\Failure',
        function() {
            strangetest\assert_less(array(1, '2', 3, 4), array(1, 2, 3, '4'));
        }
    );

    $expected = <<<'EXPECTED'
Assertion "$actual < $max" failed

-< $actual
+> $max

  array(
<     1,
<     '2',
<     3,
<     4,
>     1,
>     2,
>     3,
>     '4',
  )
EXPECTED;

    strangetest\assert_identical($actual->getMessage(), $expected);
}


function test_array_greater_than_array()
{
    $actual = strangetest\assert_throws(
        'strangetest\\Failure',
        function() {
            $array1 = array(1, array(2, (object)array(1, 2, 3), 500, 10), 5, 2);
            $array2 = array(1, array(2, (object)array(1, 1, 3),   3,  2), 5, 5);
            strangetest\assert_less($array1, $array2);
        }
    );

    $expected = <<<'EXPECTED'
Assertion "$actual < $max" failed

-< $actual
+> $max

  array(
<     1,
>     1,
      array(
<         2,
>         2,
          stdClass {
<             $0 = 1;
<             $1 = 2;
>             $0 = 1;
>             $1 = 1;
              $2 = 3;
          },
          500,
          10,
      ),
      5,
      2,
  )
EXPECTED;

    strangetest\assert_identical($actual->getMessage(), $expected);
}


function test_longer_array_greater_than_shorter_array()
{
    $actual = strangetest\assert_throws(
        'strangetest\\Failure',
        function() {
            $array1 = array(1, 2, 3, 5, 5);
            $array2 = array(      3, 4, 5);
            strangetest\assert_less($array1, $array2);
        }
    );

    $expected = <<<'EXPECTED'
Assertion "$actual < $max" failed

-< $actual
+> $max

  array(
-     1,
-     2,
<     3,
<     5,
>     3,
>     4,
      5,
  )
EXPECTED;

    strangetest\assert_identical($actual->getMessage(), $expected);
}


function test_object_greater_than_array()
{
    $actual = strangetest\assert_throws(
        'strangetest\\Failure',
        function() {
            strangetest\assert_less(new \stdClass, array());
        }
    );

    $expected = <<<'EXPECTED'
Assertion "$actual < $max" failed

-< $actual
+> $max

< stdClass {}
> array()
EXPECTED;

    strangetest\assert_identical($actual->getMessage(), $expected);
}


function test_longer_string_greater_than_shorter_string()
{
    $actual = strangetest\assert_throws(
        'strangetest\\Failure',
        function() {
            $string1 = "One\nTwo\nThree";
            $string2 = "One\nThree";
            strangetest\assert_less($string1, $string2);
        }
    );

    $expected = <<<'EXPECTED'
Assertion "$actual < $max" failed

-< $actual
+> $max

< 'One
< Two
> 'One
> Three
  Three'
EXPECTED;

    strangetest\assert_identical($actual->getMessage(), $expected);
}


function test_shorter_string_greater_than_longer_string()
{
    $actual = strangetest\assert_throws(
        'strangetest\\Failure',
        function() {
            $string1 = "One\nTwo";
            $string2 = "One\nThree\nFive";
            strangetest\assert_less($string1, $string2);
        }
    );

    $expected = <<<'EXPECTED'
Assertion "$actual < $max" failed

-< $actual
+> $max

< 'One
< Two
> 'One
> Three
  Five'
EXPECTED;

    strangetest\assert_identical($actual->getMessage(), $expected);
}


function test_common_longer_string_greater_than_shorter_string()
{
    $actual = strangetest\assert_throws(
        'strangetest\\Failure',
        function() {
            $string1 = "One\nTwo\nThree";
            $string2 = "One\nTwo";
            strangetest\assert_less($string1, $string2);
        }
    );

    $expected = <<<'EXPECTED'
Assertion "$actual < $max" failed

-< $actual
+> $max

< 'One
< Two
> 'One
> Two
  Three'
EXPECTED;

    strangetest\assert_identical($actual->getMessage(), $expected);
}
