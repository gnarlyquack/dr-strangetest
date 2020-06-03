<?php

namespace depends_fail;
use easytest;


function test_three(easytest\Context $context) {
    $context->depends('test_two');
}

function test_two(easytest\Context $context) {
    $context->depends('test_one');
}

function test_one() {
    easytest\fail('I fail');
}


class test {
    public function test_three(easytest\Context $context) {
        $context->depends('test_two');
    }

    public function test_two(easytest\Context $context) {
        $context->depends('test_one');
    }

    public function test_one() {
        easytest\fail('I fail');
    }
}
