<?php

namespace param_xdepend\nonparam;
use easytest;

function test_one(easytest\Context $context) {
    $context->set(6);
}

function test_two(easytest\Context $context) {
    $context->depends('param_xdepend\\param\\test_two');
}

function test_three(easytest\Context $context) {
    $context->depends('param_xdepend\\param\\test_four');
}

function test_four(easytest\Context $context) {
    $context->depends('param_xdepend\\param\\test_four(0)');
    $actual = $context->get('param_xdepend\\param\\test_four(0)');
    easytest\assert_identical(18, $actual);
}

function test_five(easytest\Context $context) {
    $context->depends('param_xdepend\\param\\test_four(1)');
    $actual = $context->get('param_xdepend\\param\\test_four(1)');
    easytest\assert_identical(22, $actual);
}

function test_six() {
    easytest\fail('I fail');
}
