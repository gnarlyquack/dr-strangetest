<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace strangetest;


// The functions in this file constitute Dr. Strangetest's assertion API

/**
 * @api
 * @param mixed $actual
 * @param mixed $expected
 * @param ?string $description
 * @return void
 * @throws Failure
 */
function assert_different($actual, $expected, $description = null)
{
    if ($expected !== $actual)
    {
        return;
    }

    $message = namespace\format_failure_message(
        'Assertion "$actual !== $expected" failed',
        $description,
        \sprintf('$actual = $expected = %s', namespace\format_variable($actual))
    );
    throw new Failure($message);
}


/**
 * @api
 * @param mixed $actual
 * @param mixed $expected
 * @param ?string $description
 * @return void
 * @throws Failure
 */
function assert_equal($actual, $expected, $description = null)
{
    if ($expected == $actual)
    {
        return;
    }

    $message = namespace\format_failure_message(
        'Assertion "$actual == $expected" failed',
        $description,
        namespace\diff($actual, $expected, '$actual', '$expected', namespace\DIFF_EQUAL)
    );
    throw new Failure($message);
}


/**
 * @api
 * @param mixed $actual
 * @param ?string $description
 * @return void
 * @throws Failure
 */
function assert_false($actual, $description = null)
{
    if ($actual === false)
    {
        return;
    }

    $message = namespace\format_failure_message(
        'Assertion "$actual === false" failed',
        $description,
        \sprintf('$actual = %s', namespace\format_variable($actual))
    );
    throw new Failure($message);
}


/**
 * @api
 * @param mixed $actual
 * @param ?string $description
 * @return void
 * @throws Failure
 */
function assert_falsy($actual, $description = null)
{
    if (!$actual)
    {
        return;
    }

    $message = namespace\format_failure_message(
        'Assertion "$actual == false" failed',
        $description,
        \sprintf('$actual = %s', namespace\format_variable($actual))
    );
    throw new Failure($message);
}


/**
 * @api
 * @param mixed $actual
 * @param mixed $min
 * @param ?string $description
 * @return void
 * @throws Failure
 */
function assert_greater($actual, $min, $description = null)
{
    if ($actual > $min)
    {
        return;
    }

    $message = namespace\format_failure_message(
        'Assertion "$actual > $min" failed',
        $description,
        namespace\diff($actual, $min, '$actual', '$min', namespace\DIFF_GREATER)
    );
    throw new Failure($message);
}


/**
 * @api
 * @param mixed $actual
 * @param mixed $min
 * @param ?string $description
 * @return void
 * @throws Failure
 */
function assert_greater_or_equal($actual, $min, $description = null)
{
    if ($actual >= $min)
    {
        return;
    }

    $message = namespace\format_failure_message(
        'Assertion "$actual >= $min" failed',
        $description,
        namespace\diff($actual, $min, '$actual', '$min', namespace\DIFF_GREATER_EQUAL)
    );
    throw new Failure($message);
}


/**
 * @api
 * @param mixed $actual
 * @param mixed $expected
 * @param ?string $description
 * @return void
 * @throws Failure
 */
function assert_identical($actual, $expected, $description = null)
{
    if ($expected === $actual)
    {
        return;
    }

    $message = namespace\format_failure_message(
        'Assertion "$actual === $expected" failed',
        $description,
        namespace\diff($actual, $expected, '$actual', '$expected')
    );
    throw new Failure($message);
}


/**
 * @api
 * @param mixed $actual
 * @param mixed $max
 * @param ?string $description
 * @return void
 * @throws Failure
 */
function assert_less($actual, $max, $description = null)
{
    if ($actual < $max)
    {
        return;
    }

    $message = namespace\format_failure_message(
        'Assertion "$actual < $max" failed',
        $description,
        namespace\diff($actual, $max, '$actual', '$max', namespace\DIFF_LESS)
    );
    throw new Failure($message);
}


/**
 * @api
 * @param mixed $actual
 * @param mixed $max
 * @param ?string $description
 * @return void
 * @throws Failure
 */
function assert_less_or_equal($actual, $max, $description = null)
{
    if ($actual <= $max)
    {
        return;
    }

    $message = namespace\format_failure_message(
        'Assertion "$actual <= $max" failed',
        $description,
        namespace\diff($actual, $max, '$actual', '$max', namespace\DIFF_LESS_EQUAL)
    );
    throw new Failure($message);
}


/**
 * @api
 * @template T of \Throwable
 * @param class-string<T> $expected
 * @param callable $callback
 * @param ?string $description
 * @return T
 * @throws Failure|\Exception
 */
function assert_throws($expected, $callback, $description = null)
{
    try
    {
        $callback();
    }
    // @bc 5.6 Catch Exception
    catch (\Exception $e) {}
    catch (\Throwable $e) {}

    if (!isset($e))
    {
        $message = namespace\format_failure_message(
            "Expected to catch $expected but no exception was thrown",
            $description
        );
        throw new Failure($message);
    }

    if ($e instanceof $expected)
    {
        return $e;
    }

    $message = \sprintf(
        'Expected to catch %s but instead caught %s',
        $expected, \get_class($e)
    );
    throw new \Exception($message, 0, $e);
}


/**
 * @api
 * @param mixed $actual
 * @param ?string $description
 * @return void
 * @throws Failure
 */
function assert_true($actual, $description = null)
{
    if ($actual === true)
    {
        return;
    }

    $message = namespace\format_failure_message(
        'Assertion "$actual === true" failed',
        $description,
        \sprintf('$actual = %s', namespace\format_variable($actual))
    );
    throw new Failure($message);
}


/**
 * @api
 * @param mixed $actual
 * @param ?string $description
 * @return void
 * @throws Failure
 */
function assert_truthy($actual, $description = null)
{
    if ($actual)
    {
        return;
    }

    $message = namespace\format_failure_message(
        'Assertion "$actual == true" failed',
        $description,
        \sprintf('$actual = %s', namespace\format_variable($actual))
    );
    throw new Failure($message);
}


/**
 * @api
 * @param mixed $actual
 * @param mixed $expected
 * @param ?string $description
 * @return void
 * @throws Failure
 */
function assert_unequal($actual, $expected, $description = null)
{
    if ($expected != $actual)
    {
        return;
    }

    // Since $actual and $expected may have differing (though equal) values,
    // let's display a strict diff so as not to omit any information
    if (\is_array($actual) && \is_array($expected))
    {
        namespace\ksort_recursive($actual);
        namespace\ksort_recursive($expected);
    }
    $message = namespace\format_failure_message(
        'Assertion "$actual != $expected" failed',
        $description,
        namespace\diff($actual, $expected, '$actual', '$expected')
    );
    throw new Failure($message);
}
