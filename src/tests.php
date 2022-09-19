<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace strangetest;


final class TestRunGroup extends struct
{
    /** @var int */
    public $id;

    /** @var string */
    public $path;

    /** @var TestRun[] */
    public $runs = array();

    /** @var DirectoryTest|FileTest */
    public $tests;
}


final class TestRun extends struct
{
    /**
     * @todo Remove the run id number
     * It seems like we can just use the run name directly
     *
     * @var int
     */
    public $id;

    /** @var string */
    public $name;

    /** @var DirectoryTest|FileTest */
    public $tests;

    /** @var ?\ReflectionFunction */
    public $setup;

    /** @var ?\ReflectionFunction */
    public $teardown;
}


final class DirectoryTest extends struct
{
    /** @var string */
    public $name;

    /** @var ?\ReflectionFunction */
    public $setup;

    /** @var ?\ReflectionFunction */
    public $teardown;

    /** @var array<TestRunGroup|DirectoryTest|FileTest> */
    public $tests = array();
}


final class FileTest extends struct
{
    /** @var string */
    public $name;

    /** @var ?\ReflectionFunction */
    public $setup_file;

    /** @var ?\ReflectionFunction */
    public $teardown_file;

    /** @var ?\ReflectionFunction */
    public $setup_function;

    /** @var ?\ReflectionFunction */
    public $teardown_function;

    /** @var array<ClassTest|FunctionTest> */
    public $tests = array();
}


final class FunctionTest extends struct
{
    /** @var string */
    public $name;

    /** @var \ReflectionFunction */
    public $test;
}


final class ClassTest extends struct
{
    /** @var \ReflectionClass<object> */
    public $test;

    /** @var ?\ReflectionMethod */
    public $setup_object;

    /** @var ?\ReflectionMethod */
    public $teardown_object;

    /** @var ?\ReflectionMethod */
    public $setup_method;

    /** @var ?\ReflectionMethod */
    public $teardown_method;

    /** @var MethodTest[] */
    public $tests = array();
}


final class MethodTest extends struct
{
    /** @var string */
    public $name;

    /** @var \ReflectionMethod */
    public $test;
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
        || ((0 === \strlen($default_namespace)) && (false === \strpos($default_class, '\\')))
        || ((\strlen($default_namespace) > 0) && (0 === \strpos($default_class, $default_namespace))));

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
