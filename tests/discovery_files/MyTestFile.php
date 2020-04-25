class TestTextBefore {}

<?php

/*
class TestComment {}
*/


class Test {
    function /* My test method! */ test_me() {}
}


<<<STRING
class TestString {}
STRING;


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


?>

class TestTestAfter {}
