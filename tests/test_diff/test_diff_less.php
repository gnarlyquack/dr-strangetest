<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace test\diff\less;

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
            strangetest\assert_less($from, $to);
        }
    );

    $expected = <<<EXPECTED
Assertion "\$actual < \$max" failed

-< \$actual
+> \$max

$expected
EXPECTED;

    strangetest\assert_identical($actual->getMessage(), $expected);
}


// tests

function test_equal_arrays()
{
    $from = array(1, '2', 3, 4);
    $to   = array(1, 2, 3, '4');

    $expected = <<<'EXPECTED'
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

    assert_diff($from, $to, $expected);
}


function test_array_greater_than_array()
{
    $from = array(1, array(2, (object)array(1, 2, 3), 500, 10), 5, array(5, 0));
    $to   = array(1, array(2, (object)array(1, 1, 3),   3,  2), 5, 2);

    $expected = <<<'EXPECTED'
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
      array(
          5,
          0,
      ),
  )
EXPECTED;

    assert_diff($from, $to, $expected);
}


function test_comparable_longer_array_greater_than_shorter_array()
{
    $from = array(1, 2, 3, 4, 5);
    $to   = array(1, 1, 3);

    $expected = <<<'EXPECTED'
  array(
<     1,
<     2,
>     1,
>     1,
      3,
-     4,
-     5,
  )
EXPECTED;

    assert_diff($from, $to, $expected);
}


function test_incomparable_longer_array_greater_than_shorter_array()
{
    $from = array(0,      1, 2,      3, 4,      5);
    $to   = array(   1 => 1,    3 => 2,    5 => 3, 6 => 4);

    $expected = <<<'EXPECTED'
  array(
-     0 => 0,
-     2 => 2,
-     4 => 4,
<     1 => 1,
<     3 => 3,
>     1 => 1,
>     3 => 2,
      5 => 5,
+     6 => 4,
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


function test_object_greater_than_array()
{
    $from = new \stdClass;
    $to   = array();

    $expected = <<<'EXPECTED'
< stdClass {}
> array()
EXPECTED;

    assert_diff($from, $to, $expected);
}


function test_object_greater_than_object()
{
    $from = (object)array(1, 2, 3);
    $to   = (object)array(1, 1, 3);

    $expected = <<<'EXPECTED'
  stdClass {
<     $0 = 1;
<     $1 = 2;
>     $0 = 1;
>     $1 = 1;
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
<     $two = 2;
>     $two = 2;
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


function test_longer_string_greater_than_shorter_string()
{
    $from = "One\nTwo\nThree";
    $to   = "One\nThree";

    $expected = <<<'EXPECTED'
< 'One
< Two
> 'One
> Three
  Three'
EXPECTED;

    assert_diff($from, $to, $expected);
}


function test_shorter_string_greater_than_longer_string()
{
    $from = "One\nTwo";
    $to   = "One\nThree\nFive";

    $expected = <<<'EXPECTED'
< 'One
< Two'
> 'One
> Three'
EXPECTED;

    assert_diff($from, $to, $expected);
}


function test_common_longer_string_greater_than_shorter_string()
{
    $from = "One\nTwo\nThree";
    $to   = "One\nTwo";

    $expected = <<<'EXPECTED'
< 'One
< Two
> 'One
> Two
  Three'
EXPECTED;

    assert_diff($from, $to, $expected);
}


function test_reference()
{
    $from =  array(1, 3, 3, array(6, 5, 4));
    $from[3][] =& $from[2];

    $to   =  array(1, 2, 3, array(6, 5, 4));
    $to[3][] =& $to[2];

    $expected = <<<'EXPECTED'
  array(
<     1,
<     3,
>     1,
>     2,
      3,
      array(
          6,
          5,
          4,
          &$actual[2],
      ),
  )
EXPECTED;

    assert_diff($from, $to, $expected);
}
