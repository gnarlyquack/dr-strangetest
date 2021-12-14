<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace strangetest;


const TYPE_DIRECTORY = 1;
const TYPE_FILE      = 2;
const TYPE_CLASS     = 3;
const TYPE_FUNCTION  = 4;


final class TestInfo extends struct {
    /** @var int */
    public $type;
    /** @var string */
    public $filename;
    /** @var string */
    public $namespace;
    /** @var class-string|callable-string */
    public $name;
}


final class RunInfo extends struct
{
    /** @var int */
    public $id;

    /** @var string */
    public $name;

    /** @var callable-string */
    public $setup;

    /** @var ?callable-string */
    public $teardown = null;
}


final class TestRun extends struct
{
    // @todo Remove TestRun::$name property
    // This is used to identify the file when a setup_run function returns
    // invalid (i.e., non-iterable) arguments. But instead of keeping raw
    // function names (as strings), we probably want a structure that contains
    // function definition information. Or we could use introspection, but
    // since we're already parsing files for these identifiers, it's probably
    // more efficient(?) to just save it for reference.
    /** @var string */
    public $name;

    /** @var ?RunInfo */
    public $run_info;

    /** @var array<PathTest>|array<ClassTest|FunctionTest>|array<FunctionTest> */
    public $tests;
}


final class PathTest extends struct
{
    /** @var string */
    public $name;

    /** @var int */
    public $group;

    /** @var ?callable-string */
    public $setup;

    /** @var ?callable-string */
    public $teardown;

    /** @var TestRun[] */
    public $runs;

    /** @var array<string, PathTest>|array<string, ClassTest|FunctionTest> */
    public $tests;
}


final class ClassTest extends struct {
    /** @var string */
    public $file;
    /** @var int */
    public $group;
    /** @var string */
    public $namespace;

    /** @var class-string */
    public $name;
    /** @var ?object */
    public $object;
    /** @var ?string */
    public $setup;
    /** @var ?string */
    public $teardown;

    /** @var FunctionTest[] */
    public $tests = array();
}


final class FunctionTest extends struct {
    /** @var string */
    public $file;
    /** @var string */
    public $namespace;
    /** @var ?class-string */
    public $class;
    /** @var string */
    public $function;

    /** @var string */
    public $name;
    /** @var int */
    public $group;
    /** @var ?callable(mixed ...): mixed */
    public $setup;
    /** @var ?callable(mixed ...): void */
    public $teardown;
    /** @var callable(mixed ...): void */
    public $test;
    /** @var int */
    public $result;

    /** @var ?string */
    public $setup_name;
    /** @var ?string */
    public $teardown_name;
}


/**
 * @param string $name
 * @param string $default_namespace
 * @param string $default_class
 * @return ?string
 */
function resolve_test_name($name, $default_namespace = '', $default_class = '')
{
    // Ensure that the namespace ends in a trailing namespace separator
    \assert(
        (0 === \strlen($default_namespace))
        || ('\\' === \substr($default_namespace, -1)));
    // Ensure that the namespace is included in the classname (because we
    // probably want want to change this)
    \assert(
        (0 === \strlen($default_class))
        || (0 === \strpos($default_class, $default_namespace)));

    $result = null;
    if (\preg_match(
            '~^(\\\\?(?:\\w+\\\\)*)?(\\w*::)?(\\w+)\\s*?$~',
            $name,
            $matches))
    {
        /** @var string[] $matches */
        list(, $namespace, $class, $function) = $matches;
        if ($namespace)
        {
            // This is a qualified name, so don't attempt any name resolution
            $namespace = \ltrim($namespace, '\\');
        }
        else
        {
            // Resolve the unqualified name to the current namespace and/or class
            if ($class)
            {
                $namespace = $default_namespace;
            }
            elseif ($default_class)
            {
                // the namespace is already included in the class name
                $class = $default_class . '::';
            }
            else
            {
                $namespace = $default_namespace;
            }
        }

        if ('::' === $class)
        {
            $class = '';
        }
        $result = $namespace . $class . $function;
    }
    return $result;
}
