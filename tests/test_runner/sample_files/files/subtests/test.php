<?php

namespace subtests;

use strangetest;


function test_one(strangetest\context $context) {
    $context->subtest(function() { strangetest\fail('I fail'); });
    $context->subtest(function() { strangetest\assert_true(true); });
    $context->subtest(function() { strangetest\fail('I fail again'); });
}

function test_two(strangetest\context $context) {
    $context->subtest(function() { strangetest\assert_true(true); });
    $context->subtest(function() { strangetest\assert_true(true); });
}


class test {
    public function test_one(strangetest\context $context) {
        $context->subtest(function() { strangetest\fail('I fail'); });
        $context->subtest(function() { strangetest\assert_true(true); });
        $context->subtest(function() { strangetest\fail('I fail again'); });
    }

    public function test_two(strangetest\context $context) {
        $context->subtest(function() { strangetest\assert_true(true); });
        $context->subtest(function() { strangetest\assert_true(true); });
    }
}
