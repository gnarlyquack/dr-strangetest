<?php

/*
class TestComment {}
*/


// function test_comment() {}

function test_one() {}


class Test1 {
    function // Comments between the 'function' keyword
                /* and the test method! */ TestMe() {}
}


$test_variable = null;


<<<STRING
class TestString {}
STRING;


function TestTwo () {}

class TestTwo
{
    public // visibility
        function /* as opposed to a non-function ? */ test1() {}

    public function test2() {}

    public function test3() {}
}


class NotATest {}


function some_helper_function() {}


class // valid tokens between the 'class' keyword and the test name
      /* should be handled correctly */
    test // and also comments between the class name
    /* and the opening brace */
{
    private function test_one() {}
    public function test_two() {}
    protected function test_three() {}
}


function test() {}
