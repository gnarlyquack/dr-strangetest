<?php

namespace depends_params;

use strangetest;


function setup_runs_for_file() {
    return array(
        array(1),
        array(2),
    );
}


function test_three($arg, strangetest\Context $context) {
    $actual = $context->depend_on('test_two');
    strangetest\assert_identical(2 * $arg, $actual);
}

function test_two($arg, strangetest\Context $context) {
    $actual = $context->depend_on('test_one');
    strangetest\assert_identical(2, $actual);
    $context->set($actual + $arg);
}

function test_one($arg, strangetest\Context $context) {
    $context->set($arg);
}


class test {
    private $arg;


    public function __construct($arg) {
        $this->arg = $arg;
    }


    public function test_three(strangetest\Context $context) {
        $actual = $context->depend_on('test_two');
        strangetest\assert_identical(2 * $this->arg, $actual);
    }

    public function test_two(strangetest\Context $context) {
        $actual = $context->depend_on('test_one');
        strangetest\assert_identical(2, $actual);
        $context->set($actual + $this->arg);
    }

    public function test_one(strangetest\Context $context) {
        $context->set($this->arg);
    }
}
