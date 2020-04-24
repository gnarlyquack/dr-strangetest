<?php

class TestArgumentsOne {
    private $one;
    private $two;

    public function __construct($one, $two) {
        $this->one = $one;
        $this->two = $two;
    }

    public function test() {
        easytest\assert_identical(['one', 'two'], [$this->one, $this->two]);
    }
}
