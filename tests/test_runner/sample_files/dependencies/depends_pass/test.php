<?php

namespace depends_pass;
use easytest;


function test_three(easytest\Context $context) {
    easytest\assert_identical(2, $context->depend_on('test_two'));
}

function test_two(easytest\Context $context) {
    easytest\assert_identical(1, $context->depend_on('test_one'));
    $context->set(2);
}

function test_one(easytest\Context $context) {
    $context->set(1);
}


class test {
    public function test_three(easytest\Context $context) {
        easytest\assert_identical(2, $context->depend_on('test_two'));
    }

    public function test_two(easytest\Context $context) {
        easytest\assert_identical(1, $context->depend_on('test_one'));
        $context->set(2);
    }

    public function test_one(easytest\Context $context) {
        $context->set(1);
    }
}
