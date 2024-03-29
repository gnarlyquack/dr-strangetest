<?php

namespace unrun_depends;

use strangetest;


function test_one(strangetest\Context $context) {
    $context->requires('\foobar');
}

function test_two(strangetest\Context $context) {
    $context->requires('test_one');
}


function test_three(strangetest\Context $context) {
    $context->requires('test1::test_one');
}

function test_four(strangetest\Context $context) {
    $context->requires('test_three');
}


function test_five() {
    strangetest\skip('Skip me');
}

function test_six(strangetest\Context $context) {
    $context->requires('test_five');
}



class test1 {
    public function setup_object() {
        strangetest\skip('Skip me');
    }

    public function test_one() {}

    public function test_two() {}

    public function test_three() {}
}


class test2 {
    function test_one(strangetest\Context $context) {
        $context->requires('\frobitz');
    }

    function test_two(strangetest\Context $context) {
        $context->requires('test_one');
    }


    function test_three(strangetest\Context $context) {
        $context->requires('test1::test_one');
    }

    function test_four(strangetest\Context $context) {
        $context->requires('test_three');
    }

    public function test_five() {
        strangetest\skip('Skip me');
    }

    public function test_six(strangetest\Context $context) {
        $context->requires('test_five');
    }
}
