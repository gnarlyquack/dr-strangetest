<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace test\diff\equal;

use strangetest;


// helper assertions

function assert_diff(&$from, &$to, $expected) {
    $expected = "- from\n+ to\n\n" . $expected;
    $actual = strangetest\diff($from, $to, 'from', 'to', strangetest\DIFF_EQUAL);
    strangetest\assert_identical($actual, $expected);
}


// tests

function test_formats_equal_objects() {
    $value = array('one' => 1, 'two' => 2, 'three' => 3);
    $from = (object)$value;
    $to = (object)$value;

    $expected = <<<EXPECTED
  stdClass {
      \$one = 1;
      \$two = 2;
      \$three = 3;
  }
EXPECTED;

    namespace\assert_diff($from, $to, $expected);
}


function test_diffs_unequal_arrays() {
    $from = array(
        '1 mouse',
        '2.0',
        array('0', '3', '4'),
        false,
        '5',
    );
    /*
     * reference for the actual array that is diffed against
    $to = array(
        true,
        2,
        array(0, 3, 4),
        array(),
        4,
    );
     */
    $to = array(
        4 => 4,
        3 => array(),
        2 => array(2=> 4, 1 => 3, 0 => 0),
        1 => 2,
        0 => true,
    );
    /* Ensure recursion is handled */
    $from[] = &$from;
    $to[] = &$to;

    $expected = <<<'EXPECTED'
  array(
      '1 mouse',
      '2.0',
      array(
          '0',
          '3',
          '4',
      ),
      false,
-     '5',
-     &from,
+     4,
+     &to,
  )
EXPECTED;
    namespace\assert_diff($from, $to, $expected);
}


function test_formats_diffs_unequal_objects() {
    $from = (object)array('one' => null, 'two' => true, 'three' => 'foo');
    $to = (object)array('one' => 0, 'two' => 1, 'three' => 2);

    $expected = <<<EXPECTED
  stdClass {
      \$one = NULL;
      \$two = true;
-     \$three = 'foo';
+     \$three = 2;
  }
EXPECTED;

    namespace\assert_diff($from, $to, $expected);
}
