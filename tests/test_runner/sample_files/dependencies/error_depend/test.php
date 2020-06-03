<?php

namespace error_depend;
use easytest;

function teardown_function() {
    if (test::$test === 'error_depend\\test_one') {
        \trigger_error('I erred');
    }
}


function test_one(easytest\Context $context) {
    test::$test = __FUNCTION__;
    $context->depends('test_two');
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

    public function test_one(easytest\Context $context) {
        self::$test = __FUNCTION__;
        $context->depends('test_two');
    }

    public function test_two() {
        self::$test = __FUNCTION__;
    }
}
