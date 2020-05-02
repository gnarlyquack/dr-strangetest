<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace easytest;

// #BC(5.5): Implement proxy functions for argument unpacking
// PHP 5.6's argument unpacking syntax causes a syntax error in earlier PHP
// versions, so we need to implement version-dependent proxy functions to do
// the unpacking for us. When support for PHP < 5.6 is dropped, this can all be
// eliminated and we can just use the argument unpacking syntax directly at the
// call site.

function _unpack_function($callable, $args) {
    return \call_user_func_array($callable, $args);
}


function _unpack_construct($class, $args) {
    // #BC(5.3): Save object to variable before accessing member
    $object = new \ReflectionClass($class);
    return $object->newInstanceArgs($args);
}
