<?php

namespace file_fixtures;

use strangetest;


function setup_file() {
    return array(2, 4);
}

function teardown_file($one, $two) {
    echo "$one $two";
}


function setup($one, $two) {
    echo "$one $two";
    return array($one, $two);
}

function teardown($one, $two) {
    echo "$one $two";
}


function test_one($one, $two, strangetest\Context $context) {
    $context->teardown(function () { echo "teardown 1"; });
    $context->teardown(function () { echo "teardown 2"; });
    strangetest\assert_identical(2 * $one, $two);
}

function test_two($one, $two) {
    strangetest\assert_identical(6 * $one, 3 * $two);
}


class test {
    private $one;
    private $two;

    public function __construct($one, $two) {
        $this->one = $one;
        $this->two = $two;
    }


    public function setup_object() {
        echo "{$this->one} {$this->two}";
    }

    public function teardown_object() {
        echo "{$this->one} {$this->two}";
    }


    public function setup() {
        echo "{$this->one} {$this->two}";
    }

    public function teardown() {
        echo "{$this->one} {$this->two}";
    }


    public function setup_() {
        echo __METHOD__;
    }

    public function teardown_() {
        echo __METHOD__;
    }

    public function setup_object_() {
        echo __METHOD__;
    }

    public function teardown_object_() {
        echo __METHOD__;
    }


    public function test_one(strangetest\Context $context) {
        $context->teardown(function () { echo "teardown 1"; });
        $context->teardown(function () { echo "teardown 2"; });
        strangetest\assert_identical(2 * $this->one, $this->two);
    }

    public function test_two() {
        strangetest\assert_identical(6 * $this->one, 3 * $this->two);
    }
}
