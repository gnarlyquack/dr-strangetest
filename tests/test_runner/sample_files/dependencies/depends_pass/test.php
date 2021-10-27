<?php

namespace depends_pass;

use strangetest;


function test_three(strangetest\Context $context) {
    strangetest\assert_identical(2, $context->requires('test_two'));
}

function test_two(strangetest\Context $context) {
    strangetest\assert_identical(1, $context->requires('test_one'));
    $context->set(2);
}

function test_one(strangetest\Context $context) {
    $context->set(1);
}


class test {
    public function test_three(strangetest\Context $context) {
        strangetest\assert_identical(2, $context->requires('test_two'));
    }

    public function test_two(strangetest\Context $context) {
        strangetest\assert_identical(1, $context->requires('test_one'));
        $context->set(2);
    }

    public function test_one(strangetest\Context $context) {
        $context->set(1);
    }
}
