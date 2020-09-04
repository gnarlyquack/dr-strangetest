<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace easytest;


// The functions in this file comprise EasyTest's assertion API

function assert_different($expected, $actual, $description = null) {
    if ($expected !== $actual) {
        return;
    }

    $message = namespace\format_failure_message(
        'Assertion "$expected !== $actual" failed',
        $description,
        \sprintf('$expected = $actual = %s', namespace\format_variable($actual))
    );
    throw new Failure($message);
}


function assert_equal($expected, $actual, $description = null) {
    if ($expected == $actual) {
        return;
    }

    if (\is_array($expected) && \is_array($actual)) {
        namespace\ksort_recursive($expected);
        namespace\ksort_recursive($actual);
    }
    $message = namespace\format_failure_message(
        'Assertion "$expected == $actual" failed',
        $description,
        namespace\diff($expected, $actual, '$expected', '$actual')
    );
    throw new Failure($message);
}


function assert_false($actual, $description = null) {
    if ($actual === false) {
        return;
    }

    $message = namespace\format_failure_message(
        'Assertion "$actual === false" failed',
        $description,
        \sprintf('$actual = %s', namespace\format_variable($actual))
    );
    throw new Failure($message);
}


function assert_falsy($actual, $description = null) {
    if (!$actual) {
        return;
    }

    $message = namespace\format_failure_message(
        'Assertion "$actual == false" failed',
        $description,
        \sprintf('$actual = %s', namespace\format_variable($actual))
    );
    throw new Failure($message);
}


function assert_greater($actual, $min, $description = null) {
    if ($actual > $min) {
        return;
    }

    $message = namespace\format_failure_message(
        "Assertion \"\$actual > $min\" failed",
        $description,
        \sprintf('$actual = %s', namespace\format_variable($actual))
    );
    throw new Failure($message);
}


function assert_greater_or_equal($actual, $min, $description = null) {
    if ($actual >= $min) {
        return;
    }

    $message = namespace\format_failure_message(
        "Assertion \"\$actual >= $min\" failed",
        $description,
        \sprintf('$actual = %s', namespace\format_variable($actual))
    );
    throw new Failure($message);
}


function assert_identical($expected, $actual, $description = null) {
    if ($expected === $actual) {
        return;
    }

    $message = namespace\format_failure_message(
        'Assertion "$expected === $actual" failed',
        $description,
        namespace\diff($expected, $actual, '$expected', '$actual')
    );
    throw new Failure($message);
}


function assert_less($actual, $max, $description = null) {
    if ($actual < $max) {
        return;
    }

    $message = namespace\format_failure_message(
        "Assertion \"\$actual < $max\" failed",
        $description,
        \sprintf('$actual = %s', namespace\format_variable($actual))
    );
    throw new Failure($message);
}


function assert_less_or_equal($actual, $max, $description = null) {
    if ($actual <= $max) {
        return;
    }

    $message = namespace\format_failure_message(
        "Assertion \"\$actual <= $max\" failed",
        $description,
        \sprintf('$actual = %s', namespace\format_variable($actual))
    );
    throw new Failure($message);
}


function assert_throws($expected, $callback, $description = null) {
    try {
        $callback();
    }
    // #BC(5.6): Catch Exception
    catch (\Exception $e) {}
    catch (\Throwable $e) {}

    if (!isset($e)) {
        $message = namespace\format_failure_message(
            "Expected to catch $expected but no exception was thrown",
            $description
        );
        throw new Failure($message);
    }

    if ($e instanceof $expected) {
        return $e;
    }

    $message = \sprintf(
        'Expected to catch %s but instead caught %s',
        $expected, \get_class($e)
    );
    throw new \Exception($message, 0, $e);
}


function assert_true($actual, $description = null) {
    if ($actual === true) {
        return;
    }

    $message = namespace\format_failure_message(
        'Assertion "$actual === true" failed',
        $description,
        \sprintf('$actual = %s', namespace\format_variable($actual))
    );
    throw new Failure($message);
}


function assert_truthy($actual, $description = null) {
    if ($actual) {
        return;
    }

    $message = namespace\format_failure_message(
        'Assertion "$actual == true" failed',
        $description,
        \sprintf('$actual = %s', namespace\format_variable($actual))
    );
    throw new Failure($message);
}


function assert_unequal($expected, $actual, $description = null) {
    if ($expected != $actual) {
        return;
    }

    // Since $expected and $actual may have differing (though equal) values,
    // let's display a diff so as not to omit any information
    if (\is_array($expected) && \is_array($actual)) {
        namespace\ksort_recursive($expected);
        namespace\ksort_recursive($actual);
    }
    $message = namespace\format_failure_message(
        'Assertion "$expected != $actual" failed',
        $description,
        namespace\diff($expected, $actual, '$expected', '$actual')
    );
    throw new Failure($message);
}
