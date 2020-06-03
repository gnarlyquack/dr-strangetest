<?php

namespace param_xdepend\param;
use easytest;

function setup_file() {
    return easytest\arglists(array(array(2), array(4)));
}


function test_six($arg, easytest\Context $context) {
    $context->depends('test_five');
    $actual = $context->get('test_five');
    easytest\assert_identical(5 * $arg + 6, $actual);
    $context->set($arg + $actual);
}

function test_five($arg, easytest\Context $context) {
    $context->depends('param_xdepend\\nonparam\\test_six ()');
    $actual = $context->get('test_four');
    easytest\assert_identical(4 * $arg + 6, $actual);
    $context->set($arg + $actual);
}

function test_four($arg, easytest\Context $context) {
    $context->depends('test_three');
    $actual = $context->get('test_three');
    easytest\assert_identical(3 * $arg + 6, $actual);
    $context->set($arg + $actual);
}

function test_three($arg, easytest\Context $context) {
    $context->depends('test_two');
    $actual = $context->get('test_two');
    easytest\assert_identical(14, $actual);
    $context->set($arg + $actual);
}

function test_two($arg, easytest\Context $context) {
    $context->depends('test_one');
    $actual = $context->get('test_one');
    easytest\assert_identical($arg + 6, $actual);
    $context->set($arg + $actual);
}

function test_one($arg, easytest\Context $context) {
    $context->depends('param_xdepend\\nonparam\\test_one()');
    $actual = $context->get('param_xdepend\\nonparam\\test_one()');
    easytest\assert_identical(6, $actual);
    $context->set($arg + $actual);
}
