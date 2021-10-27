<?php

namespace depends_fail;

use strangetest;


function test_three(strangetest\Context $context) {
    $context->requires('test_two');
}

function test_two(strangetest\Context $context) {
    $context->requires('test_one');
}

function test_one() {
    strangetest\fail('I fail');
}


class test {
    public function test_three(strangetest\Context $context) {
        $context->requires('test_two');
    }

    public function test_two(strangetest\Context $context) {
        $context->requires('test_one');
    }

    public function test_one() {
        strangetest\fail('I fail');
    }
}
