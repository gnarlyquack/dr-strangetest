<?php

namespace any_noniterable_arglist;

use strangetest;


function setup_run0() {
    return 1;
}

function teardown_run0($one) {
    echo $one;
}


function setup_run1() {
    return array(2, 3);
}

function teardown_run1($one, $two) {
    echo "$one $two";
}


function setup_run2() {
    return 4;
}

function teardown_run2($one) {
    echo $one;
}



function teardown_file($one, $two) {
    echo "$one $two";
}


function test_one($one, $two) {
    strangetest\assert_identical(array(2, 3), array($one, $two));
}
