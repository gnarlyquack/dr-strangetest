<?php

namespace test_use_group;

use function three\ {one, three\one as one_again, three\two};
use three\ {
    One,
    function one as one_fun,
    function three\two as two_again,
    three\two};


function test() {}
