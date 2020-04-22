<?php

namespace conditional;

if (true) {

    class TestA {}

}
elseif (false) {

    class TestA {
        public function __construct() {
            assert(false, 'I should not have been discovered!');
        }
    }


    class TestB {
        public function __construct() {
            assert(false, 'I should not have been discovered!');
        }
    }


    class TestC {
        public function __construct() {
            assert(false, 'I should not have been discovered!');
        }
    }

}
