<?php

namespace param_xdepend\nonparam;

use strangetest;
use param_xdepend\param;


function test_two(strangetest\Context $context) {
    $context->requires('param\\test_two');
}

function test_three(strangetest\Context $context) {
    $context->requires('param\\test_four');
}

function test_four(strangetest\Context $context) {
    $actual = $context->requires('param\\test_four');
    strangetest\assert_identical(18, $actual);
}


// The order of test_five and test_one ensures that test_five is postponed with
// a dependency on test_one and that the run number of parameterized test_four
// isn't associated with non-parameterized test_one, otherwise test_five will
// never complete since it will be waiting on a non-existent test run
function test_five(strangetest\Context $context) {
    $actual = $context->requires(
        'test_one',
        'param\\test_four'
    );
    strangetest\assert_identical(22, $actual['param\\test_four']);
}

function test_one(strangetest\Context $context) {
    $context->set(6);
}

function test_six() {
    strangetest\fail('I fail');
}
