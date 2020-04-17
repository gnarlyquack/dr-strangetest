<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

function expect_fails($assert) {
    try {
        $assert();
    }
    catch (AssertionError $f) {}

    if (!isset($f)) {
        throw new easytest\Failure('assertion did not fail');
    }
    return $f;
}


class TestAssertExpression {

    public function test_uses_default_description() {
        $f = easytest\assert_exception(
            'easytest\\Failure',
            function() {
                $true = 1;
                $false = 0;
                assert($true == $false);
            }
        );

        // #BC(5.6): Check format of default assert description
        $expected = version_compare(PHP_VERSION, '7.0.0', '<')
                  ? 'Assertion failed'
                  : 'assert($true == $false)';
        easytest\assert_identical($expected, $f->getMessage());
    }


    public function test_uses_provided_description() {
        // #BC(5.4): Check if assert() takes description parameter
        if (version_compare(PHP_VERSION, '5.4.8', '<')) {
            easytest\skip(
                "assert() description parameter was added in PHP 5.4.8"
            );
        }

        $expected = 'My assertion failed. Or did it?';
        $f = easytest\assert_exception(
            'easytest\\Failure',
            function() use ($expected) { assert(true == false, $expected); }
        );

        easytest\assert_identical($expected, $f->getMessage());
    }


    public function test_uses_exception_as_description() {
        // #BC(5.4): Check if assert() takes description parameter
        if (version_compare(PHP_VERSION, '5.4.8', '<')) {
            easytest\skip(
                "assert() description parameter was added in PHP 5.4.8"
            );
        }

        $expected = new ExpectedException();
        $f = easytest\assert_exception(
            'easytest\\Failure',
            function() use ($expected) { assert(true == false, $expected); }
        );

        easytest\assert_identical("$expected", $f->getMessage());
    }
}



// #BC(7.1): Test assert() with a string expression
class TestAssertString {

    public function setup_class() {
        if (version_compare(PHP_VERSION, '7.2', '>=')) {
            easytest\skip('PHP 7.2 deprecates calling assert() with a string');
        }
    }


    public function test_uses_assert_expression_as_default_message() {
        $f = easytest\assert_exception(
            'easytest\\Failure',
            function() { assert('true == false'); });
        easytest\assert_identical('Assertion "true == false" failed', $f->getMessage());
    }


    public function test_uses_provided_description() {
        // #BC(5.4): Check if assert() takes description parameter
        if (version_compare(PHP_VERSION, '5.4.8', '<')) {
            easytest\skip(
                "assert() description parameter wasn't added until PHP 5.4.8"
            );
        }

        $expected = 'My assertion failed. Or did it?';
        $f = easytest\assert_exception(
            'easytest\\Failure',
            function() use ($expected) { assert('true == false', $expected); }
        );

        easytest\assert_identical($expected, $f->getMessage());
    }


    public function test_uses_exception_as_description() {
        // #BC(5.4): Check if assert() takes description parameter
        if (version_compare(PHP_VERSION, '5.4.8', '<')) {
            easytest\skip(
                "assert() description parameter was added in PHP 5.4.8"
            );
        }

        $expected = new ExpectedException();
        $f = easytest\assert_exception(
            'easytest\\Failure',
            function() use ($expected) { assert('true == false', $expected); }
        );

        easytest\assert_identical("$expected", $f->getMessage());
    }


    /*
     * Having more (or less) than two variables in the assertion context
     * should output the value of each variable.
     */
    public function test_parses_variable_values() {
        $f = easytest\assert_exception(
            'easytest\\Failure',
            function() {
                $one = 1;
                $two = 2;
                $four = 4;
                assert('$one + $two == $four');
            }
        );

        $expected = <<<'EXPECTED'
Assertion "$one + $two == $four" failed

one:
1

two:
2

four:
4
EXPECTED;
        easytest\assert_identical($expected, $f->getMessage());
    }


    /*
     * Two variables in the assertion context (which is expected to be the
     * most common case) should produce a diff-style output.
     */
    public function test_shows_diff_for_two_variables() {
        $f = easytest\assert_exception(
            'easytest\\Failure',
            function() {
                $one = true;
                $two = false;
                assert('$one == $two');
            }
        );

        $expected = <<<'EXPECTED'
Assertion "$one == $two" failed

- one
+ two

- true
+ false
EXPECTED;
        easytest\assert_identical($expected, $f->getMessage());
    }
}



class TestExpectExpression {

    private $assert_exception;


    // #BC(5.6): Check if PHP 7 expectations are supported
    public function setup_class() {
        if (version_compare(PHP_VERSION, '7.0', '<')) {
            easytest\skip('Skipping tests of PHP 7 expectations');
        }
    }


    public function setup() {
        $this->assert_exception = ini_get('assert.exception');
        ini_set('assert.exception', true);
    }


    public function teardown() {
        ini_set('assert.exception', $this->assert_exception);
    }


    public function test_uses_default_description() {
        $f = expect_fails(
            function() {
                $true = 1;
                $false = 0;
                assert($true == $false);
            }
        );

        easytest\assert_identical('assert($true == $false)', $f->getMessage());
    }


    public function test_uses_provided_description() {
        $expected = 'My assertion failed. Or did it?';
        $f = expect_fails(
            function() use ($expected) { assert(true == false, $expected); }
        );

        easytest\assert_identical($expected, $f->getMessage());
    }


    public function test_throws_provided_exception() {
        $expected = new ExpectedException();
        try {
            assert(true == false, $expected);
        }
        catch (ExpectedException $actual) {}

        easytest\assert_identical($expected, $actual);
    }


    public function test_does_not_throw_old_assertion() {
        expect_fails(function() { assert(true == false); });
        try {
            trigger_error('This should be an error');
        }
        catch (\Throwable $actual) {}

        easytest\assert_identical('easytest\\Error', get_class($actual));
        easytest\assert_identical('This should be an error', $actual->getMessage());
    }
}



// #BC(7.1): Test assert() with a string expression
class TestExpectString {

    private $assert_exception;


    // #BC(5.6): Check if PHP 7 expectations are supported
    public function setup_class() {
        if (version_compare(PHP_VERSION, '7.0', '<')) {
            easytest\skip('Skipping tests of PHP 7 expectations');
        }
        if (version_compare(PHP_VERSION, '7.2', '>=')) {
            easytest\skip('PHP 7.2 deprecates calling assert() with a string');
        }
    }


    public function setup() {
        $this->assert_exception = ini_get('assert.exception');
        ini_set('assert.exception', true);
    }


    public function teardown() {
        ini_set('assert.exception', $this->assert_exception);
    }


    public function test_has_no_default_message() {
        $f = expect_fails(
            function() {
                $one = true;
                $two = false;
                assert('$one == $two');
            }
        );
        easytest\assert_identical('', $f->getMessage());
    }


    public function test_uses_provided_description() {
        $expected = 'My assertion failed. Or did it?';
        $f = expect_fails(
            function() use ($expected) { assert('true == false', $expected); }
        );

        easytest\assert_identical($expected, $f->getMessage());
    }


    public function test_throws_provided_exception() {
        $expected = new ExpectedException();
        try {
            assert('true == false', $expected);
        }
        catch (ExpectedException $actual) {}

        easytest\assert_identical($expected, $actual);
    }
}
