<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace test\diff\greater_equal;

use strangetest;

use test\diff\Incomparable1;
use test\diff\Incomparable2;


// helper assertions

function assert_diff($from, $to, $expected)
{
    $actual = strangetest\assert_throws(
        'strangetest\\Failure',
        function() use ($from, $to)
        {
            strangetest\assert_greater_or_equal($from, $to);
        }
    );

    $expected = <<<EXPECTED
Assertion "\$actual >= \$min" failed

-> \$actual
+< \$min

$expected
EXPECTED;
    strangetest\assert_identical($actual->getMessage(), $expected);
}


// tests

function test_array_less_than_array()
{
    $from = array(1, array(2, (object)array(1, 1, 3),   3,  2), 5, 5);
    $to   = array(1, array(2, (object)array(1, 2, 3), 500, 10), 5, 2);

    $expected = <<<'EXPECTED'
  array(
      1,
      array(
          2,
          stdClass {
              $0 = 1;
>             $1 = 1;
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

    assert_diff($from, $to, $expected);
}


function test_comparable_shorter_array_less_than_longer_array()
{
    $from = array(1, 1, 3);
    $to   = array(1, 2, 3, 4, 5);

    $expected = <<<'EXPECTED'
  array(
      1,
>     1,
<     2,
      3,
+     4,
+     5,
  )
EXPECTED;

    assert_diff($from, $to, $expected);
}


function test_incomparable_shorter_array_less_than_longer_array()
{
    $from = array(   1 => 1,    3 => 2,    5 => 3, 6 => 4);
    $to   = array(0,      1, 2,      3, 4,      5);

    $expected = <<<'EXPECTED'
  array(
      1 => 1,
>     3 => 2,
<     3 => 3,
      5 => 3,
-     6 => 4,
+     0 => 0,
+     2 => 2,
+     4 => 4,
  )
EXPECTED;

    assert_diff($from, $to, $expected);
}


function test_incomparable_arrays()
{
    $from = array('one' => 1, 'two' => 2);
    $to   = array(1, 2);

    $expected = <<<'EXPECTED'
  array(
-     'one' => 1,
-     'two' => 2,
+     0 => 1,
+     1 => 2,
  )
EXPECTED;

    assert_diff($from, $to, $expected);
}


function test_array_less_than_object()
{
    $from = array();
    $to   = new \stdClass;

    $expected = <<<'EXPECTED'
> array()
< stdClass {}
EXPECTED;

    assert_diff($from, $to, $expected);
}


function test_object_less_than_object()
{
    $from = (object)array(1, 1, 3);
    $to   = (object)array(1, 2, 3);

    $expected = <<<'EXPECTED'
  stdClass {
      $0 = 1;
>     $1 = 1;
<     $1 = 2;
      $2 = 3;
  }
EXPECTED;

    assert_diff($from, $to, $expected);
}


function test_incomparable_objects_of_same_class()
{
    $from = (object)array('one' => 1, 'two' => 2);
    $to   = (object)array('two' => 2, 'three' => 3);

    $expected = <<<'EXPECTED'
  stdClass {
-     $one = 1;
      $two = 2;
+     $three = 3;
  }
EXPECTED;

    assert_diff($from, $to, $expected);
}


function test_incomparable_objects_of_different_class()
{
    $from = new Incomparable1;
    $to   = new Incomparable2;

    $expected = <<<'EXPECTED'
- test\diff\Incomparable1 {
-     $foo = 'foo';
- }
+ test\diff\Incomparable2 {
+     $foo = 'foo';
+ }
EXPECTED;

    assert_diff($from, $to, $expected);
}


function test_longer_string_less_than_shorter_string()
{
    $from = "One\nThree\nFive";
    $to   = "One\nTwo";

    $expected = <<<'EXPECTED'
  'One
> Three
< Two
  Five'
EXPECTED;

    assert_diff($from, $to, $expected);
}


function test_shorter_string_less_than_longer_string()
{
    $from = "Two\nOne";
    $to   = "Two\nThree\nFour";

    $expected = <<<'EXPECTED'
  'Two
> One'
< Three'
EXPECTED;

    assert_diff($from, $to, $expected);
}


function test_common_shorter_string_less_than_longer_string()
{
    $from = "One\nTwo";
    $to   = "One\nTwo\nThree";

    $expected = <<<'EXPECTED'
  'One
  Two
+ Three'
EXPECTED;

    assert_diff($from, $to, $expected);
}
