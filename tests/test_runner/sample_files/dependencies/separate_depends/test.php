<?php

namespace separate_depends;

use strangetest;


function test_one(strangetest\Context $context) {
    $context->depend_on('test::test_two');
    $context->depend_on('test_three');
    $context->depend_on('test::test_four');
}

function test_three(strangetest\Context $context) {
    $context->depend_on('test::test_two');
    $context->depend_on('test_seven');
}

function test_five(strangetest\Context $context) {
    $context->depend_on('test::test_six');
    $context->depend_on('test::test_nine');
}

function test_seven() {}

function test_ten() {}



class test {
    public function __construct() {
        echo '.';
    }

    public function test_two(strangetest\Context $context) {
        $context->depend_on('::test_five');
        $context->depend_on('test_six');
    }

    public function test_four(strangetest\Context $context) {
        $context->depend_on('test_eight');
        $context->depend_on('test_nine');
    }

    public function test_six() {}

    public function test_eight() {
        strangetest\fail('I fail');
    }

    public function test_nine(strangetest\Context $context) {
        $context->depend_on('::test_ten');
    }
}
