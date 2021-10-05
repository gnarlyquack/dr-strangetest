<?php

namespace multiple_depends;

use strangetest;


function test_one(strangetest\Context $context) {
    $context->depend_on('test::test_two', 'test_three', 'test::test_four');
}

function test_three(strangetest\Context $context) {
    $context->depend_on('test::test_two', 'test_seven');
}

function test_five(strangetest\Context $context) {
    $context->depend_on('test::test_six', 'test::test_nine');
}

function test_seven() {}

function test_ten() {}



class test {
    public function __construct() {
        echo '.';
    }

    public function test_two(strangetest\Context $context) {
        $context->depend_on('::test_five', 'test_six');
    }

    public function test_four(strangetest\Context $context) {
        $context->depend_on('test_eight', 'test_nine');
    }

    public function test_six() {}

    public function test_eight() {
        strangetest\fail('I fail');
    }

    public function test_nine(strangetest\Context $context) {
        $context->depend_on('::test_ten');
    }
}
