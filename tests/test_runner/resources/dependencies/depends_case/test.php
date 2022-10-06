<?php

namespace depends_case;

use strangetest;


function TEST_THREE(strangetest\Context $context) {
    strangetest\assert_identical(2, $context->requires('test_two'));
}

function TEST_TWO(strangetest\Context $context) {
    strangetest\assert_identical(1, $context->requires('test_one'));
    $context->set(2);
}

function TEST_ONE(strangetest\Context $context) {
    $context->set(1);
}


class TEST {
    public function TEST_THREE(strangetest\Context $context) {
        strangetest\assert_identical(2, $context->requires('test_two'));
    }

    public function TEST_TWO(strangetest\Context $context) {
        strangetest\assert_identical(1, $context->requires('test_one'));
        $context->set(2);
    }

    public function TEST_ONE(strangetest\Context $context) {
        $context->set(1);
    }
}
