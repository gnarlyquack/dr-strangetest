<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
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
        catch (strangetest\Error $actual) {}

        if (!isset($actual)) {
            throw new strangetest\Failure('Error exception wasn\'t thrown');
        }

        $expected = sprintf(
            "%s\nin %s on line %s\n\nStack trace:\n%s",
            $message,
            $file,
            $line,
            $actual->getTraceAsString()
        );
        strangetest\assert_identical($expected, "$actual");
    }


    public function test_fail_throws_failure_exception() {
        $message = 'Fail! :-(';
        $file = __FILE__;
        try {
            $line = __LINE__ + 1;
            strangetest\fail($message);
        }
        catch (strangetest\Failure $actual) {}

        if (!isset($actual)) {
            strangetest\fail('Failure exception wasn\'t thrown');
        }

        strangetest\assert_identical($actual->getMessage(), $message);
    }


    public function test_skip_throws_skip_exception() {
        $message = 'Test skipped';
        $file = __FILE__;
        try {
            $line = __LINE__ + 1;
            strangetest\skip($message);
        }
        catch (strangetest\Skip $actual) {}

        if (!isset($actual)) {
            strangetest\fail('Skip exception not thrown');
        }

        strangetest\assert_identical($actual->getMessage(), $message);
    }
}
