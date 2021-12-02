<?php

namespace FileCase;

use strangetest;


function SetUpFile() {
    return array(2, 4);
}

function TearDownFile($one, $two) {
    echo "$one $two";
}


function SetUpFunction($one, $two) {
    echo "$one $two";
    return array($one, $two);
}

function TearDownFunction($one, $two) {
    echo "$one $two";
}


function TestOne($one, $two) {
    strangetest\assert_identical(2 * $one, $two);
}

function TestTwo($one, $two) {
    strangetest\assert_identical(6 * $one, 3 * $two);
}


class Test {
    private $one;
    private $two;

    public function __construct($one, $two) {
        $this->one = $one;
        $this->two = $two;
    }


    public function SetUpObject() {
        echo "{$this->one} {$this->two}";
    }

    public function TearDownObject() {
        echo "{$this->one} {$this->two}";
    }


    public function SetUp() {
        echo "{$this->one} {$this->two}";
    }

    public function TearDown() {
        echo "{$this->one} {$this->two}";
    }


    public function TestOne() {
        strangetest\assert_identical(2 * $this->one, $this->two);
    }

    public function TestTwo() {
        strangetest\assert_identical(6 * $this->one, 3 * $this->two);
    }
}
