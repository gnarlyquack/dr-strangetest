<?php

namespace condition;

require_once 'conditional_c.php';


if (true) {
    class TestB
    {
        public function test()
        {
        }
    }

    function test_two()
    {
    }
}
elseif (false)
{
    class TestA
    {
        public function test()
        {
        }
    }

    class TestB
    {
        public function test()
        {
        }
    }

    class TestC
    {
        public function test()
        {
        }
    }


    function test_one()
    {
    }

    function test_two()
    {
    }

    function test_three()
    {
    }
}
