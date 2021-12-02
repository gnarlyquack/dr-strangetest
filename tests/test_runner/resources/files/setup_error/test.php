<?php

namespace setup_error;


function setup_file() {
    echo '.';
}

function teardown_file() {
    echo '.';
}


function setup_function() {
    \trigger_error('An error happened');
}

function teardown_function() {
    echo '.';
}


function test_one() {}

function test_two() {}


class test {
    public function setup_object() {
        echo '.';
    }

    public function teardown_object() {
        echo '.';
    }

    public function setup() {
        \trigger_error('An error happened');
    }

    public function teardown() {
        echo '.';
    }

    public function test_one() {}

    public function test_two() {}
}
