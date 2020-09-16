<?php

namespace definitions;

use function easytest\assert_true;


trait TestTrait {
    function test_three() {}
    function test_four() {}
}

interface TestInterface {
    function test_five();
    function test_six();
}

abstract class TestAbstract {
    function test_seven() {}
    function test_eight() {}
}

class Test {
    function test_one() {}

    function test_two() {}
}
