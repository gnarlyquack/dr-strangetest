class TestTextBefore {}

<?php

/*
class TestComment {}
*/


class Test {
    function /* My test method! */ test_me() {}
}


function Test() {}


<<<STRING
class TestString {}
STRING;


function testTwo() {}


class test2 {
    public function testOne() {}
    public function testTwo() {}
    public function testThree() {}
}


class NotATest {}


class // valid tokens between the 'class' keyword and the test name
      /* should be handled correctly */
TestThree {
    private function test1() {}
    public function test2() {}
    protected function test3() {}
}


function // valid tokens between the 'function' keyword and the test name
         /* should be handled correctly */
test_three() {
}

?>

class TestTextAfter {}
