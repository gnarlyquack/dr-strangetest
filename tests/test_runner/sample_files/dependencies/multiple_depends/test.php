<?php

namespace multiple_depends;
use easytest;


function test_one(easytest\Context $context) {
    $context->depends('test::test_two', 'test_three', 'test::test_four');
}

function test_three(easytest\Context $context) {
    $context->depends('test::test_two', 'test_seven');
}

function test_five(easytest\Context $context) {
    $context->depends('test::test_six', 'test::test_nine');
}

function test_seven() {}

function test_ten() {}



class test {
    public function __construct() {
        echo '.';
    }

    public function test_two(easytest\Context $context) {
        $context->depends('::test_five', 'test_six');
    }

    public function test_four(easytest\Context $context) {
        $context->depends('test_eight', 'test_nine');
    }

    public function test_six() {}

    public function test_eight() {
        easytest\fail('I fail');
    }

    public function test_nine(easytest\Context $context) {
        $context->depends('::test_ten');
    }
}
