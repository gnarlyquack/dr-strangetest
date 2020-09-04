<?php

namespace param_xdepend\nonparam;
use easytest;

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


// The order of test_five and test_one ensures that test_five is postponed with
// a dependency on test_one and that the run number of parameterized test_four
// isn't associated with non-parameterized test_one, otherwise test_five will
// never complete since it will be waiting on a non-existent test run
function test_five(easytest\Context $context) {
    $context->depends(
        'test_one',
        'param_xdepend\\param\\test_four(1)'
    );
    $actual = $context->get('param_xdepend\\param\\test_four(1)');
    easytest\assert_identical(22, $actual);
}

function test_one(easytest\Context $context) {
    $context->set(6);
}

function test_six() {
    easytest\fail('I fail');
}
