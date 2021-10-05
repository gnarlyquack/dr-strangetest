<?php

namespace skip_object;

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


function test_one() {}



class test {
    public function setup_object() {
        strangetest\skip('Skip me');
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

    public function test_one() {}

    public function test_two() {}
}


function test_two() {}
