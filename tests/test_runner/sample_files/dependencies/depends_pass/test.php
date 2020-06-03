<?php

namespace depends_pass;
use easytest;


function test_three(easytest\Context $context) {
    $context->depends('test_two');
    easytest\assert_identical(2, $context->get('test_two'));
}

function test_two(easytest\Context $context) {
    $context->depends('test_one');
    easytest\assert_identical(1, $context->get('test_one'));
    $context->set(2);
}

function test_one(easytest\Context $context) {
    $context->set(1);
}


class test {
    public function test_three(easytest\Context $context) {
        $context->depends('test_two');
        easytest\assert_identical(2, $context->get('test_two'));
    }

    public function test_two(easytest\Context $context) {
        $context->depends('test_one');
        easytest\assert_identical(1, $context->get('test_one'));
        $context->set(2);
    }

    public function test_one(easytest\Context $context) {
        $context->set(1);
    }
}
