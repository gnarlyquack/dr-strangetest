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
    public function test1() {}
    public function test2() {}
    public function test3() {}
}

class NotATest {}

class // valid tokens between the 'class' keyword and the test name
      /* should be handled correctly */
Test3 {}

?>

class TestTestAfter {}
