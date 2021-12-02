<?php

namespace buffering;

use strangetest;


function setup_function() {
    echo 'setup output that should be seen';
    ob_start();
    echo 'setup output that should not be seen';
}

function teardown_function() {
    echo 'teardown output that should not be seen';
    ob_end_clean();
    echo 'teardown output that should be seen';
}


function test_skip() {
    echo __FUNCTION__;
    strangetest\skip('Skip me');
}

function test_error() {
    echo __FUNCTION__;
    trigger_error('Did I err?');
}

function test_fail() {
    echo __FUNCTION__;
    strangetest\fail('I failed');
}


class test {
    public function setup() {
        echo 'setup output that should be seen';
        ob_start();
        echo 'setup output that should not be seen';
    }

    public function teardown() {
        echo 'teardown output that should not be seen';
        ob_end_clean();
        echo 'teardown output that should be seen';
    }

    public function test_skip() {
        echo __FUNCTION__;
        strangetest\skip('Skip me');
    }

    public function test_error() {
        echo __FUNCTION__;
        trigger_error('Did I err?');
    }

    public function test_fail() {
        echo __FUNCTION__;
        strangetest\fail('I failed');
    }

    public function test_pass() {
        echo __FUNCTION__;
    }
}


function test_pass() {
    echo __FUNCTION__;
}
