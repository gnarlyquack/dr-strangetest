<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace easytest;

// @bc 5.5 Implement proxy functions for argument unpacking
// PHP 5.6's argument unpacking syntax causes a syntax error in earlier PHP
// versions, so we need to implement version-dependent proxy functions to do
// the unpacking for us. When support for PHP < 5.6 is dropped, this can all be
// eliminated and we can just use the argument unpacking syntax directly at the
// call site.

/**
 * @template T
 * @param callable(mixed...): T $callable
 * @param mixed[] $args
 * @return T
 */
function unpack_function($callable, array $args) {
    return $callable(...$args);
}


/**
 * @template T of object
 * @param class-string<T> $class
 * @param mixed[] $args
 * @return T
 */
function unpack_construct($class, array $args) {
    return new $class(...$args);
}
