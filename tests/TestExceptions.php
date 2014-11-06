<?php

class TestExceptions {
    public function test_error_format() {
        $file = __FILE__;
        $line = __LINE__;
        $message = 'An error happened';
        $e = new easytest\Error($message, E_USER_ERROR, $file, $line);

        $expected = sprintf(
            "%s\nin %s on line %s\nStack trace:\n%s",
            $message,
            $file,
            $line,
            $e->getTraceAsString()
        );
        $actual = (string)$e;
        assert('$expected === $actual');
    }

    public function test_failure_format() {
        $expected = 'Assertion failed';
        $f = new easytest\Failure($expected);
        $actual = (string)$f;
        assert('$expected === $actual');
    }
}
