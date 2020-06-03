<?php

namespace subtests;
use easytest;


function test_one(easytest\context $context) {
    $context->subtest(function() { easytest\fail('I fail'); });
    $context->subtest(function() { easytest\assert_true(true); });
    $context->subtest(function() { easytest\fail('I fail again'); });
}

function test_two(easytest\context $context) {
    $context->subtest(function() { easytest\assert_true(true); });
    $context->subtest(function() { easytest\assert_true(true); });
}


class test {
    public function test_one(easytest\context $context) {
        $context->subtest(function() { easytest\fail('I fail'); });
        $context->subtest(function() { easytest\assert_true(true); });
        $context->subtest(function() { easytest\fail('I fail again'); });
    }

    public function test_two(easytest\context $context) {
        $context->subtest(function() { easytest\assert_true(true); });
        $context->subtest(function() { easytest\assert_true(true); });
    }
}
