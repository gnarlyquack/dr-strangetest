<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace test\diff\less_equal;

use strangetest;


// helper assertions

function assert_diff(&$from, &$to, $expected) {
    $expected = "-< from\n+> to\n\n" . $expected;
    $actual = strangetest\diff($from, $to, 'from', 'to', strangetest\DIFF_LESS_EQUAL);
    strangetest\assert_identical($actual, $expected);
}


// tests

function test_array_greater_than_array()
{
    $from = array(1, 500, 5,  2, 5);
    $to   = array(1,   3, 2, 10, 5);

    $expected = <<<'EXPECTED'
  array(
      1,
<     500,
>     3,
      5,
      2,
      5,
  )
EXPECTED;

    assert_diff($from, $to, $expected);
}


function test_longer_array_greater_than_shorter_array()
{
    $from = array(1, array(2, (object)array(1, 2, 3), 500, 10), 5, 2);
    $to   = array(1, array(2, (object)array(1, 1, 3),   3,  2), 5, 5);

    $expected = <<<'EXPECTED'
  array(
      1,
      array(
          2,
          stdClass {
              $0 = 1;
<             $1 = 2;
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

    assert_diff($from, $to, $expected);
}


/*
class Boo
{
    var $one = 1;
    var $two = 2;
}

class Far
{
    var $three = 1;
    var $four = 2;
}


function test_incomparable_objects()
{
    $one = new Boo;
    $two = new Far;
    strangetest\assert_less_or_equal($one, $two);
}
*/


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


function test_longer_string_greater_than_shorter_string()
{
    $from = "One\nTwo\nThree";
    $to   = "One\nThree";

    $expected = <<<'EXPECTED'
  'One
< Two
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
  'One
< Two
> Three
  Five'
EXPECTED;

    assert_diff($from, $to, $expected);
}


function test_common_longer_string_greater_than_shorter_string()
{
    $from = "One\nTwo\nThree";
    $to   = "One\nTwo";

    $expected = <<<'EXPECTED'
  'One
  Two
- Three'
EXPECTED;

    assert_diff($from, $to, $expected);
}
