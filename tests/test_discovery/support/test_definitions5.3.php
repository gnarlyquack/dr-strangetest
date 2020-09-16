<?php

namespace definitions;


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
