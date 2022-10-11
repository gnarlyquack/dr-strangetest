<?php

namespace test_use_function;

use function three;
use function three as classes;
use function three\One;
use function three\Two as ClassTwo;
use function three\one as OneAgain, four, three\two as TwoTwo, three\four\five;

function test() {}
