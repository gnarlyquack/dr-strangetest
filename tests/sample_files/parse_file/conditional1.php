<?php

namespace conditional;

if (true) {

    class TestA {
        public function test() {}
    }

    function test_a() {}

}
elseif (false) {

    class TestA {
        public function __construct() {
            trigger_error('I should not have been discovered!');
        }
    }


    function test_a() {}


    class TestB {
        public function __construct() {
            trigger_error('I should not have been discovered!');
        }
    }


    function test_b() {}


    class TestC {
        public function __construct() {
            trigger_error('I should not have been discovered!');
        }
    }


    function test_c() {}

}
