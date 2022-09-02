<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace test\diff\greater_equal;

use strangetest;


// helper assertions

function assert_diff(&$from, &$to, $expected) {
    $expected = "-> from\n+< to\n\n" . $expected;
    $actual = strangetest\diff($from, $to, 'from', 'to', strangetest\DIFF_GREATER_EQUAL);
    strangetest\assert_identical($actual, $expected);
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


function test_shorter_array_less_than_longer_array()
{
    $from = array(      3, 3, 5);
    $to   = array(1, 2, 3, 4, 5);

    $expected = <<<'EXPECTED'
  array(
+     1,
+     2,
      3,
>     3,
<     4,
      5,
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
