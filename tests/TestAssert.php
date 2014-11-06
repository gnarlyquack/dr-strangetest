<?php

class TestAssert {
    public function test_failed_assertion() {
        try {
            assert(true == false);
            throw new \Exception("Failed assertion didn't trigger an exception");
        }
        catch (easytest\Failure $e) {}

        $actual = $e->getMessage();
        assert('"Assertion failed" === $actual');
    }

    public function test_failed_assertion_with_code() {
        try {
            assert('true == false');
            throw new \Exception("Failed assertion didn't trigger an exception");
        }
        catch (easytest\Failure $e) {}

        $expected = 'Assertion "true == false" failed';
        $actual = $e->getMessage();
        assert('$expected === $actual');
    }

    public function test_failed_assertion_with_message() {
        if (version_compare(PHP_VERSION, '5.4.8') < 0) {
            // The assert() description parameter was added in PHP 5.4.8, so
            // skip this test if this is an earlier version of PHP.
            return;
        }

        $expected = 'My assertion failed. Or did it?';
        try {
            assert('true == false', $expected);
            throw new \Exception("Failed assertion didn't trigger an exception");
        }
        catch (easytest\Failure $e) {}

        $actual = $e->getMessage();
        assert('$expected === $actual');
    }

    public function test_failed_assertion_with_variables() {
        $one = true;
        $two = false;
        try {
            assert('$one == $two');
            throw new \Exception("Failed assertion didn't trigger an exception");
        }
        catch (easytest\Failure $e) {}

        $expected = <<<'EXPECTED'
Assertion "$one == $two" failed

one:
true

two:
false
EXPECTED;
        $actual = $e->getMessage();
        assert('$expected === $actual');
    }
}
