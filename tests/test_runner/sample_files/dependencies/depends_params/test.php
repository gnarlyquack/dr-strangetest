<?php

namespace depends_params;
use easytest;


function setup_file() {
    return easytest\arglists(array(
        array(1),
        array(2),
    ));
}


function test_three($arg, easytest\Context $context) {
    $actual = $context->depend_on('test_two');
    easytest\assert_identical(2 * $arg, $actual);
}

function test_two($arg, easytest\Context $context) {
    $actual = $context->depend_on('test_one');
    easytest\assert_identical(2, $actual);
    $context->set($actual + $arg);
}

function test_one($arg, easytest\Context $context) {
    $context->set($arg);
}


class test {
    private $arg;


    public function __construct($arg) {
        $this->arg = $arg;
    }


    public function test_three(easytest\Context $context) {
        $actual = $context->depend_on('test_two');
        easytest\assert_identical(2 * $this->arg, $actual);
    }

    public function test_two(easytest\Context $context) {
        $actual = $context->depend_on('test_one');
        easytest\assert_identical(2, $actual);
        $context->set($actual + $this->arg);
    }

    public function test_one(easytest\Context $context) {
        $context->set($this->arg);
    }
}
