<?php

namespace unrun_depends;
use easytest;


function test_one(easytest\Context $context) {
    $context->depends('\foobar');
}

function test_two(easytest\Context $context) {
    $context->depends('test_one');
}


function test_three(easytest\Context $context) {
    $context->depends('test1::test_one');
}

function test_four(easytest\Context $context) {
    $context->depends('test_three');
}


function test_five() {
    easytest\skip('Skip me');
}

function test_six(easytest\Context $context) {
    $context->depends('test_five');
}



class test1 {
    public function setup_object() {
        easytest\skip('Skip me');
    }

    public function test_one() {}

    public function test_two() {}

    public function test_three() {}
}


class test2 {
    function test_one(easytest\Context $context) {
        $context->depends('\frobitz');
    }

    function test_two(easytest\Context $context) {
        $context->depends('test_one');
    }


    function test_three(easytest\Context $context) {
        $context->depends('test1::test_one');
    }

    function test_four(easytest\Context $context) {
        $context->depends('test_three');
    }

    public function test_five() {
        easytest\skip('Skip me');
    }

    public function test_six(easytest\Context $context) {
        $context->depends('test_five');
    }
}
