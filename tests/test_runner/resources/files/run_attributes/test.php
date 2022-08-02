<?php

use strangetest\attribute\Test;

/*
class TestComment {}
*/


// function test_comment() {}

#[Test]
function function_one() {}


#[Test]
class Class1 {
    #[Test]
    function // Comments between the 'function' keyword
                /* and the test method! */ MethodToTest() {}
}


$test_variable = null;


<<<STRING
class TestString {}
STRING;


#[Test]
function NumberTwo () {}


#[Test]
class NumberTwo
{
    #[Test]
    public // visibility
        function /* as opposed to a non-function ? */ number1() {}

    #[Test]
    public function number2() {}

    #[Test]
    public function number3() {}
}
