<?php

namespace anonymous;
use easytest;


function test_anonymous_class() {
    $class = new class {};
    easytest\assert_true(\is_object($class));
}

function test_i_am_a_function_name() {
}


class test {
    public function test_anonymous_class() {
        $this->test_i_am_a_method_name(new class {});
    }

    private function test_i_am_a_method_name($class) {}
}
