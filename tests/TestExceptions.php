<?php
/*
 * EasyTest
 * Copyright (c) 2014 Karl Nack
 *
 * This file is subject to the license terms in the LICENSE file found in the
 * top-level directory of this distribution. No part of this project,
 * including this file, may be copied, modified, propagated, or distributed
 * except according to the terms contained in the LICENSE file.
 */

class TestExceptions {
    public function test_error_format() {
        $file = __FILE__;
        $line = __LINE__;
        $message = 'An error happened';
        $e = new easytest\Error($message, E_USER_ERROR, $file, $line);

        $expected = sprintf(
            "%s\nin %s on line %s\n\nStack trace:\n%s",
            $message,
            $file,
            $line,
            $e->getTraceAsString()
        );
        $actual = (string)$e;
        easytest\assert_identical($expected, $actual);
    }

    public function test_failure_format() {
        $expected = 'Assertion failed';
        $f = new easytest\Failure($expected);
        $actual = (string)$f;
        easytest\assert_identical($expected, $actual);
    }

    public function test_skip_format() {
        $expected = 'Test skipped';
        $s = new easytest\Skip($expected);
        $actual = (string)$s;
        easytest\assert_identical($expected, $actual);
    }
}
