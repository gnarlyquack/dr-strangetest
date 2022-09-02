<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace test\diff\less;

use strangetest;


// helper assertions

function assert_diff(&$from, &$to, $expected) {
    $expected = "-< from\n+> to\n\n" . $expected;
    $actual = strangetest\diff($from, $to, 'from', 'to', strangetest\DIFF_LESS);
    strangetest\assert_identical($actual, $expected);
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
    $from = array(1, array(2, (object)array(1, 2, 3), 500, 10), 5, 2);
    $to   = array(1, array(2, (object)array(1, 1, 3),   3,  2), 5, 5);

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
      2,
  )
EXPECTED;

    assert_diff($from, $to, $expected);
}


function test_longer_array_greater_than_shorter_array()
{
    $from = array(1, 2, 3, 5, 5);
    $to   = array(      3, 4, 5);

    $expected = <<<'EXPECTED'
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
< Two
> 'One
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
< 'One
< Two
> 'One
> Two
  Three'
EXPECTED;

    assert_diff($from, $to, $expected);
}
