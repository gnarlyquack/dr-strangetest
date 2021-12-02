<?php

namespace undeleted_buffers;


function setup_file() {
    ob_start();
    echo __FUNCTION__;
}

function teardown_file() {
    ob_start();
    echo __FUNCTION__;
}

function setup_function() {
    ob_start();
    echo __FUNCTION__;
}

function teardown_function() {
    ob_start();
    echo __FUNCTION__;
}


function test() {
    ob_start();
}


class test {
    public function setup_object() {
        ob_start();
        echo __METHOD__;
    }

    public function teardown_object() {
        ob_start();
        echo __METHOD__;
    }

    public function setup() {
        ob_start();
        echo __METHOD__;
    }

    public function teardown() {
        ob_start();
        echo __METHOD__;
    }

    public function test() {
        ob_start();
    }
}
