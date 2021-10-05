<?php

namespace param_xdepend\param;

use strangetest;


function setup_runs() {
    return array(
        array(2),
        array(4)
    );
}


function test_six($arg, strangetest\Context $context) {
    $actual = $context->depend_on('test_five');
    strangetest\assert_identical(5 * $arg + 6, $actual);
    $context->set($arg + $actual);
}

function test_five($arg, strangetest\Context $context) {
    $actual = $context->depend_on('param_xdepend\\nonparam\\test_six ()');
    strangetest\assert_identical(4 * $arg + 6, $actual);
    $context->set($arg + $actual);
}

function test_four($arg, strangetest\Context $context) {
    $actual = $context->depend_on('test_three');
    strangetest\assert_identical(3 * $arg + 6, $actual);
    $context->set($arg + $actual);
}

function test_three($arg, strangetest\Context $context) {
    $actual = $context->depend_on('test_two');
    strangetest\assert_identical(14, $actual);
    $context->set($arg + $actual);
}

function test_two($arg, strangetest\Context $context) {
    $actual = $context->depend_on('test_one');
    strangetest\assert_identical($arg + 6, $actual);
    $context->set($arg + $actual);
}

function test_one($arg, strangetest\Context $context) {
    $actual = $context->depend_on('param_xdepend\\nonparam\\test_one()');
    strangetest\assert_identical(6, $actual);
    $context->set($arg + $actual);
}
