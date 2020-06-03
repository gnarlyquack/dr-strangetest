<?php

namespace teardown_test_error;
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
    $context->teardown(function () { easytest\skip('Skip me'); });
    $context->teardown(function () { echo 'teardown 2'; });
}

function test_two() {}


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
        $context->teardown(function () { easytest\skip('Skip me'); });
        $context->teardown(function () { echo 'teardown 2'; });
    }


    public function test_two() {}
}
