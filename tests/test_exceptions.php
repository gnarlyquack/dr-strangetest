<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

class TestExceptions {
    public function test_error_throws_error_exception() {
        $message = 'An error happened';
        $file = __FILE__;
        try {
            $line = __LINE__ + 1;
            trigger_error($message);
        }
        catch (easytest\Error $actual) {}

        if (!isset($actual)) {
            throw new easytest\Failure('Error exception wasn\'t thrown');
        }

        $expected = sprintf(
            "%s\nin %s on line %s\n\nStack trace:\n%s",
            $message,
            $file,
            $line,
            $actual->getTraceAsString()
        );
        easytest\assert_identical($expected, "$actual");
    }


    public function test_fail_throws_failure_exception() {
        $message = 'Fail! :-(';
        $file = __FILE__;
        try {
            $line = __LINE__ + 1;
            easytest\fail($message);
        }
        catch (easytest\Failure $actual) {}

        if (!isset($actual)) {
            throw new easytest\Failure('Failure exception wasn\'t thrown');
        }

        $expected = <<<MSG
$message

in $file on line $line
MSG;
        easytest\assert_identical($expected, "$actual");
    }


    public function test_fail_shows_call_trace() {
        $message = 'Fail! :-(';
        $file = __FILE__;
        $class = __CLASS__;
        $lines = array(__LINE__ + 1);
        $actual = $this->helper_one($message, $lines);
        $expected = <<<MSG
$message

in $file on line $lines[4]

Called from:
$file($lines[3]): easytest\\assert_throws()
$file($lines[2]): ${class}->fail()
$file($lines[1]): ${class}->helper_two()
$file($lines[0]): ${class}->helper_one()
MSG;
        easytest\assert_identical($expected, "$actual");
    }

    private function helper_one($message, &$lines) {
        $lines[] = __LINE__ + 1;
        return $this->helper_two($message, $lines);
    }

    private function helper_two($message, &$lines) {
        $lines[] = __LINE__ + 1;
        return $this->fail($message, $lines);
    }

    private function fail($message, &$lines) {
        // #BC(5.6): Adjust the reported line on which a function is called
        $lines[] = version_compare(PHP_VERSION, '7.0', '<')
                 ? __LINE__ + 8
                 : __LINE__ + 6;
        return easytest\assert_throws(
            'easytest\\Failure',
            function() use ($message, &$lines) {
                $lines[] = __LINE__ + 1;
                easytest\fail($message);
            }
        );
    }


    public function test_skip_throws_skip_exception() {
        $message = 'Test skipped';
        $file = __FILE__;
        try {
            $line = __LINE__ + 1;
            easytest\skip($message);
        }
        catch (easytest\Skip $actual) {}

        if (!isset($actual)) {
            throw new easytest\Failure('Skip exception not thrown');
        }

        $expected = <<<MSG
$message
in $file on line $line
MSG;
        easytest\assert_identical($expected, "$actual");
    }
}
