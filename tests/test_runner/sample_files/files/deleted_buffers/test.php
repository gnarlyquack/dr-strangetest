<?php

namespace deleted_buffers;


function setup_file() {
    echo __FUNCTION__;
    ob_end_clean();
}

function teardown_file() {
    echo __FUNCTION__;
    ob_end_clean();
}

function setup_function() {
    echo __FUNCTION__;
    ob_end_clean();
}

function teardown_function() {
    echo __FUNCTION__;
    ob_end_clean();
}

function test() {
    echo __FUNCTION__;
    ob_end_clean();
}


class test {
    public function setup_object() {
        echo __METHOD__;
        ob_end_clean();
    }

    public function teardown_object() {
        echo __METHOD__;
        ob_end_clean();
    }

    public function setup() {
        echo __METHOD__;
        ob_end_clean();
    }

    public function teardown() {
        echo __METHOD__;
        ob_end_clean();
    }

    public function test() {
        echo __METHOD__;
        ob_end_clean();
    }
}
