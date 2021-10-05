<?php

namespace teardown_error;

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
    strangetest\skip('Skip me');
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
        echo '.';
    }

    public function teardown() {
        strangetest\skip('Skip me');
    }

    public function test_one() {}

    public function test_two() {}
}
