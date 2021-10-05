<?php

class TestArgumentsTwo {
    private $two;
    private $three;

    public function __construct($two, $three) {
        $this->two = $two;
        $this->three = $three;
    }

    public function test() {
        strangetest\assert_identical(
            array('two', 'three'),
            array($this->two, $this->three)
        );
    }
}
