<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace easytest;


// The functions in this file comprise EasyTest's assertion API

function assert_throws($expected, $callback, $message = null) {
    try {
        $callback();
    }
    catch (\Throwable $e) {}
    // #BC(5.6): Catch Exception
    catch (\Exception $e) {}

    if (!isset($e)) {
        throw new Failure(
            $message ?: 'No exception was thrown although one was expected'
        );
    }

    if ($e instanceof $expected) {
        return $e;
    }

    throw $e;
}


/*
 * assert_equal() and assert_identical() are simply proxies for static methods
 * on the ErrorHandler. This is done to support the $message parameter in
 * versions of PHP < 5.4.8.
 */

function assert_equal($expected, $actual, $message = null) {
    if ($expected == $actual) {
        return;
    }

    if (\is_array($expected) && \is_array($actual)) {
        namespace\ksort_recursive($expected);
        namespace\ksort_recursive($actual);
    }
    if (!isset($message)) {
        $message = 'Assertion "$expected == $actual" failed';
    }
    throw new Failure(
        \sprintf(
            "%s\n\n%s",
            $message,
            namespace\diff(
                namespace\format_variable($expected),
                namespace\format_variable($actual),
                'expected', 'actual'
            )
        )
    );
}


function assert_identical($expected, $actual, $message = null) {
    if ($expected === $actual) {
        return;
    }

    if (!isset($message)) {
        $message = 'Assertion "$expected === $actual" failed';
    }
    throw new Failure(
        \sprintf(
            "%s\n\n%s",
            $message,
            namespace\diff(
                namespace\format_variable($expected),
                namespace\format_variable($actual),
                'expected', 'actual'
            )
        )
    );
}
