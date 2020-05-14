class TestTextBefore {}

<?php

/*
class TestComment {}
*/


class Test1 {
    function /* My test method! */ test_me() {}
}


<<<STRING
class TestString {}
STRING;


class test_two {
    public function test1() {}
    public function test2() {}
    public function test3() {}
}


class NoTest {}


class // valid tokens between the 'class' keyword and the test name
      /* should be handled correctly */
Test3 {
    private function test_one() {}
    public function test_two() {}
    protected function test_three() {}
}


?>

class TestTestAfter {}
