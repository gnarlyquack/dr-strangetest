<?php

namespace skip_setup;
use easytest;


function setup_file() {
    echo '.';
}

function teardown_file() {
    echo '.';
}


function setup_function() {
    easytest\skip('Skip me');
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
        easytest\skip('Skip me');
    }

    public function teardown() {
        echo '.';
    }

    public function test_one() {}

    public function test_two() {}
}
