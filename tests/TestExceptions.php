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
        $message = 'An error happened';
        $file = __FILE__;
        $line = __LINE__;
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
        $message = 'Assertion failed';
        $file = __FILE__;
        $line = __LINE__ + 1;
        $actual = new easytest\Failure($message);

        $expected = <<<MSG
Assertion failed

in $file on line $line
MSG;
        easytest\assert_identical($expected, "$actual");
    }


    public function test_failure_format_with_trace() {
        $file = __FILE__;
        $class = __CLASS__;
        $lines = [__LINE__ + 1];
        $actual = $this->helper_one($lines);
        $expected = <<<MSG
easytest\\Failure thrown

in $file on line $lines[4]

Called from:
$file($lines[3]): easytest\\assert_exception()
$file($lines[2]): ${class}->fail()
$file($lines[1]): ${class}->helper_two()
$file($lines[0]): ${class}->helper_one()
MSG;
        easytest\assert_identical($expected, "$actual");
    }

    private function helper_one(&$lines) {
        $lines[] = __LINE__ + 1;
        return $this->helper_two($lines);
    }

    private function helper_two(&$lines) {
        $lines[] = __LINE__ + 1;
        return $this->fail($lines);
    }

    private function fail(&$lines) {
        // #BC(5.6): Adjust the reported line on which a function is called
        $lines[] = version_compare(PHP_VERSION, '7.0', '<')
                 ? __LINE__ + 8
                 : __LINE__ + 6;
        return easytest\assert_exception(
            'easytest\\Failure',
            function() use (&$lines) {
                $lines[] = __LINE__ + 1;
                throw new easytest\Failure();
            }
        );
    }


    public function test_skip_format() {
        $expected = 'Test skipped';
        $s = new easytest\Skip($expected);
        $actual = (string)$s;
        easytest\assert_identical($expected, $actual);
    }
}
