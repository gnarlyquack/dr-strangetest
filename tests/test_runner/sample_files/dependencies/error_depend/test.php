<?php

namespace error_depend;

use strangetest;


function teardown_function() {
    if (test::$test === 'error_depend\\test_one') {
        \trigger_error('I erred');
    }
}


function test_one(strangetest\Context $context) {
    test::$test = __FUNCTION__;
    $context->depend_on('test_two');
}

function test_two() {
    test::$test = __FUNCTION__;
}



class test {
    static public $test;

    public function teardown() {
        if (self::$test === 'test_one') {
            \trigger_error('I erred');
        }
    }

    public function test_one(strangetest\Context $context) {
        self::$test = __FUNCTION__;
        $context->depend_on('test_two');
    }

    public function test_two() {
        self::$test = __FUNCTION__;
    }
}
