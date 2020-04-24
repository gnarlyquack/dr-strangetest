<?php

class TestArgumentsTwo {
    private $two;
    private $three;

    public function __construct($two, $three) {
        $this->two = $two;
        $this->three = $three;
    }

    public function test() {
        easytest\assert_identical(['two', 'three'], [$this->two, $this->three]);
    }
}
