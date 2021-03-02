<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

class TestAssertExpression {

    private $assert_exception;


    public function setup_object() {
        // #BC(5.6): Check if PHP 7 expectations are supported
        if (version_compare(PHP_VERSION, '7.0', '>=')) {
            $this->assert_exception = ini_get('assert.exception');
            ini_set('assert.exception', false);
        }
    }


    public function teardown_object() {
        // #BC(5.6): Check if PHP 7 expectations are supported
        if (version_compare(PHP_VERSION, '7.0', '>=')) {
            ini_set('assert.exception', $this->assert_exception);
        }
    }



    public function test_uses_default_description() {
        $f = easytest\assert_throws(
            'easytest\\Failure',
            function() {
                $true = 1;
                $false = 0;
                assert($true == $false);
            }
        );

        // #BC(5.6): Check format of default assert description
        $expected = version_compare(PHP_VERSION, '7.0', '<')
                  ? 'Assertion failed'
                  : 'assert($true == $false)';
        easytest\assert_identical($expected, $f->getMessage());
    }


    public function test_uses_provided_description() {
        // #BC(5.4): Check if assert() takes description parameter
        if (version_compare(PHP_VERSION, '5.4.8', '<')) {
            easytest\skip('PHP 5.4.8 added assert() $description parameter');
        }

        $expected = 'My assertion failed. Or did it?';
        $f = easytest\assert_throws(
            'easytest\\Failure',
            function() use ($expected) { assert(true == false, $expected); }
        );

        easytest\assert_identical($expected, $f->getMessage());
    }


    public function test_uses_exception_as_description() {
        // @bc 5.4 Check if assert() takes description parameter
        if (version_compare(PHP_VERSION, '5.4.8', '<')) {
            easytest\skip('PHP 5.4.8 added assert() $description parameter');
        }

        // @bc 7.4 Check if assert() uses exception as the description
        // If an exception is provided as the description, PHP 8 appears to
        // throw it regardless of the setting of assert.exception.
        if (version_compare(PHP_VERSION, '8.0', '<')) {
            $expected = new ExpectedException();
            $f = easytest\assert_throws(
                'easytest\\Failure',
                function() use ($expected) { assert(true == false, $expected); }
            );
            easytest\assert_identical("$expected", $f->getMessage());
        }
        else {
            $expected = new ExpectedException();
            $f = easytest\assert_throws(
                get_class($expected),
                function() use ($expected) { assert(true == false, $expected); }
            );
            easytest\assert_identical($expected, $f);
        }

    }
}



// #BC(7.1): Test assert() with a string expression
class TestAssertString {

    private $assert_exception;


    public function setup_object() {
        if (version_compare(PHP_VERSION, '7.2', '>=')) {
            easytest\skip('PHP 7.2 deprecated calling assert() with a string');
        }
        // #BC(5.6): Check if PHP 7 expectations are supported
        if (version_compare(PHP_VERSION, '7.0', '>=')) {
            $this->assert_exception = ini_get('assert.exception');
            ini_set('assert.exception', false);
        }
    }


    public function teardown_object() {
        // #BC(5.6): Check if PHP 7 expectations are supported
        if (version_compare(PHP_VERSION, '7.0', '>=')) {
            ini_set('assert.exception', $this->assert_exception);
        }
    }



    public function test_uses_assert_expression_as_default_message() {
        $f = easytest\assert_throws(
            'easytest\\Failure',
            function() {
                $true = 1;
                $false = 0;
                assert('$true == $false');
            }
        );

        $expected = 'assert($true == $false) failed';
        easytest\assert_identical($expected, $f->getMessage());
    }


    public function test_uses_provided_description() {
        // #BC(5.4): Check if assert() takes description parameter
        if (version_compare(PHP_VERSION, '5.4.8', '<')) {
            easytest\skip('PHP 5.4.8 added assert() $description parameter');
        }

        $expected = 'My assertion failed. Or did it?';
        $f = easytest\assert_throws(
            'easytest\\Failure',
            function() use ($expected) { assert('true == false', $expected); }
        );

        easytest\assert_identical(
            "assert(true == false) failed\n$expected",
            $f->getMessage()
        );
    }


    public function test_uses_exception_as_description() {
        // #BC(5.4): Check if assert() takes description parameter
        if (version_compare(PHP_VERSION, '5.4.8', '<')) {
            easytest\skip('PHP 5.4.8 added assert() $description parameter');
        }

        $expected = new ExpectedException();
        $f = easytest\assert_throws(
            'easytest\\Failure',
            function() use ($expected) { assert('true == false', $expected); }
        );

        easytest\assert_identical(
            "assert(true == false) failed\n$expected",
            $f->getMessage()
        );
    }
}



class TestExpectExpression {

    private $assert_exception;


    public function setup_object() {
        // #BC(5.6): Check if PHP 7 expectations are supported
        if (version_compare(PHP_VERSION, '7.0', '<')) {
            easytest\skip('PHP 7 introduced "expectations"');
        }
        $this->assert_exception = ini_get('assert.exception');
        ini_set('assert.exception', true);
    }


    public function teardown_object() {
        ini_set('assert.exception', $this->assert_exception);
    }



    public function test_uses_default_description() {
        $f = easytest\assert_throws(
            'AssertionError',
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
        $f = easytest\assert_throws(
            'AssertionError',
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
        easytest\assert_throws(
            'AssertionError',
            function() { assert(true == false); }
        );
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


    public function setup_object() {
        if (version_compare(PHP_VERSION, '7.2', '>=')) {
            easytest\skip('PHP 7.2 deprecated calling assert() with a string');
        }
        // #BC(5.6): Check if PHP 7 expectations are supported
        if (version_compare(PHP_VERSION, '7.0', '<')) {
            easytest\skip('PHP 7 introduced "expectations"');
        }
        $this->assert_exception = ini_get('assert.exception');
        ini_set('assert.exception', true);
    }


    public function teardown_object() {
        ini_set('assert.exception', $this->assert_exception);
    }



    public function test_has_no_default_message() {
        $f = easytest\assert_throws(
            'AssertionError',
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
        $f = easytest\assert_throws(
            'AssertionError',
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
