<?php

namespace file_failures;

use strangetest;


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


function test_one(strangetest\Context $context) {
    $context->teardown(function() { echo 'teardown'; });
    strangetest\fail('I failed');
}

function test_two(strangetest\Context $context) {
    $context->teardown(function() { echo 'teardown'; });
    \trigger_error('An error happened');
}


function test_three(strangetest\Context $context) {
    $context->teardown(function() { echo 'teardown'; });
    @$foo['bar'];
}

function test_four(strangetest\Context $context) {
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

    public function test_one(strangetest\Context $context) {
        $context->teardown(function() { echo 'teardown'; });
        strangetest\fail('I failed');
    }

    public function test_two(strangetest\Context $context) {
        $context->teardown(function() { echo 'teardown'; });
        \trigger_error('An error happened');
    }

    function test_three(strangetest\Context $context) {
        $context->teardown(function() { echo 'teardown'; });
        $foo = @$this->bar;
    }

    public function test_four(strangetest\Context $context) {
        $context->teardown(function() { echo 'teardown'; });
        throw new \Exception("I'm exceptional!");
    }
}
