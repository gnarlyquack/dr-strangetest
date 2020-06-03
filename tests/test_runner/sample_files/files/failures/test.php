<?php

namespace file_failures;

use easytest;


function setup_file() {
    echo '.';
}

function teardown_file() {
    echo '.';
}


function setup_function() {
    echo '.';
}

function teardown_function() {
    echo '.';
}


function test_one(easytest\Context $context) {
    $context->teardown(function() { echo 'teardown'; });
    easytest\fail('I failed');
}

function test_two(easytest\Context $context) {
    $context->teardown(function() { echo 'teardown'; });
    \trigger_error('An error happened');
}


function test_three(easytest\Context $context) {
    $context->teardown(function() { echo 'teardown'; });
    @$foo['bar'];
}

function test_four(easytest\Context $context) {
    $context->teardown(function() { echo 'teardown'; });
    throw new \Exception("I'm exceptional!");
}


class test {
    public function setup_object() {
        echo '.';
    }

    public function teardown_object() {
        echo '.';
    }

    public function setup() {
        echo '.';
    }

    public function teardown() {
        echo '.';
    }

    public function test_one(easytest\Context $context) {
        $context->teardown(function() { echo 'teardown'; });
        easytest\fail('I failed');
    }

    public function test_two(easytest\Context $context) {
        $context->teardown(function() { echo 'teardown'; });
        \trigger_error('An error happened');
    }

    function test_three(easytest\Context $context) {
        $context->teardown(function() { echo 'teardown'; });
        $foo = @$this->bar;
    }

    public function test_four(easytest\Context $context) {
        $context->teardown(function() { echo 'teardown'; });
        throw new \Exception("I'm exceptional!");
    }
}
