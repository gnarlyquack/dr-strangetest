class TestTextBefore {}

<?php

/*
class TestComment {}
*/


class Test {}


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
Test3 {}


?>

class TestTestAfter {}
