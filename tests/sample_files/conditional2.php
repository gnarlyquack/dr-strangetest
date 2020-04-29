<?php

namespace conditional;

if (true) {

    class TestB {
        public function test() {}
    }


    function test_b() {}


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
