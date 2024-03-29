<?php

namespace cyclical_depends;

use strangetest;


function test_one(strangetest\Context $context) {
    $context->requires('test_two');
}

function test_two(strangetest\Context $context) {
    $context->requires('test_three');
}

function test_three(strangetest\Context $context) {
    $context->requires('test_four');
}

function test_four(strangetest\Context $context) {
    $context->requires('test_five');
}

function test_five(strangetest\Context $context) {
    $context->requires('test_three');
}


class test {
    public function test_one(strangetest\Context $context) {
        $context->requires('test_two');
    }

    public function test_two(strangetest\Context $context) {
        $context->requires('test_three');
    }

    public function test_three(strangetest\Context $context) {
        $context->requires('test_four');
    }

    public function test_four(strangetest\Context $context) {
        $context->requires('test_five');
    }

    public function test_five(strangetest\Context $context) {
        $context->requires('test_three');
    }
}
