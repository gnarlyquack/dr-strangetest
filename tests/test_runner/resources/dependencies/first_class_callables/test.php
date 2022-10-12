<?php

namespace first_class_callables;

use strangetest\{Context, function assert_identical};


function test_three(Context $context) {
    assert_identical(2, $context->requires(test_two(...)));
}

function test_two(Context $context) {
    assert_identical(1, $context->requires(test_one(...)));
    $context->set(2);
}

function test_one(Context $context) {
    $context->set(1);
}


class test {
    public function test_three(Context $context) {
        assert_identical(4, $context->requires($this->test_two(...)));
    }

    public function test_two(Context $context) {
        assert_identical(3, $context->requires($this->test_one(...)));
        $context->set(4);
    }

    public function test_one(Context $context) {
        $context->set(3);
    }
}
