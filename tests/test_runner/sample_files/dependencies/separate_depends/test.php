<?php

namespace separate_depends;
use easytest;


function test_one(easytest\Context $context) {
    $context->depends('test::test_two');
    $context->depends('test_three');
    $context->depends('test::test_four');
}

function test_three(easytest\Context $context) {
    $context->depends('test::test_two');
    $context->depends('test_seven');
}

function test_five(easytest\Context $context) {
    $context->depends('test::test_six');
    $context->depends('test::test_nine');
}

function test_seven() {}

function test_ten() {}



class test {
    public function __construct() {
        echo '.';
    }

    public function test_two(easytest\Context $context) {
        $context->depends('::test_five');
        $context->depends('test_six');
    }

    public function test_four(easytest\Context $context) {
        $context->depends('test_eight');
        $context->depends('test_nine');
    }

    public function test_six() {}

    public function test_eight() {
        easytest\fail('I fail');
    }

    public function test_nine(easytest\Context $context) {
        $context->depends('::test_ten');
    }
}
