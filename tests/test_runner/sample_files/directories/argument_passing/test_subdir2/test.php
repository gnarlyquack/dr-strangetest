<?php

class TestArgumentsThree {
    private $one;
    private $two;

    public function __construct($one, $two) {
        $this->one = $one;
        $this->two = $two;
    }

    public function test() {
        strangetest\assert_identical(
            array('one', 'two'),
            array($this->one, $this->two)
        );
    }
}
