<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace strangetest;


final class FunctionInfo extends struct
{
    /** @var callable-string Full, namespace-qualified name of function */
    public $name;

    /** @var string */
    public $namespace;

    /** @var string Function name without the namespace */
    public $short_name;

    /** @var string */
    public $file;

    /** @var int */
    public $line;
}


final class ClassInfo extends struct
{
    /** @var class-string Full, namespace-qualified name of class */
    public $name;

    /** @var string */
    public $namespace;

    /** @var string */
    public $file;

    /** @var int */
    public $line;
}


final class MethodInfo extends struct
{
    /** @var ClassInfo */
    public $class;

    /** @var string The method name (without the class name prepended) */
    public $name;

    /** @var string */
    public $file;

    /** @var int */
    public $line;
}


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
    /** @var string */
    public $name;

    /** @var DirectoryTest|FileTest */
    public $tests;

    /** @var ?FunctionInfo */
    public $setup;

    /** @var ?FunctionInfo */
    public $teardown;
}


final class DirectoryTest extends struct
{
    /** @var string */
    public $name;

    /** @var ?FunctionInfo */
    public $setup;

    /** @var ?FunctionInfo */
    public $teardown;

    /** @var array<TestRunGroup|DirectoryTest|FileTest> */
    public $tests = array();
}


final class FileTest extends struct
{
    /** @var string */
    public $name;

    /** @var ?FunctionInfo */
    public $setup_file;

    /** @var ?FunctionInfo */
    public $teardown_file;

    /** @var ?FunctionInfo */
    public $setup_function;

    /** @var ?FunctionInfo */
    public $teardown_function;

    /** @var array<ClassTest|FunctionTest> */
    public $tests = array();
}


final class FunctionTest extends struct
{
    /** @var string */
    public $name;

    /** @var string case-insensitive test identifier*/
    public $hash;

    /** @var FunctionInfo */
    public $test;
}


final class ClassTest extends struct
{
    /** @var ClassInfo */
    public $test;

    /** @var ?MethodInfo */
    public $setup_object;

    /** @var ?MethodInfo */
    public $teardown_object;

    /** @var ?MethodInfo */
    public $setup_method;

    /** @var ?MethodInfo */
    public $teardown_method;

    /** @var MethodTest[] */
    public $tests = array();
}


final class MethodTest extends struct
{
    /** @var string */
    public $name;

    /** @var string case-insensitive test identifier*/
    public $hash;

    /** @var MethodInfo */
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
    // @fixme Correctly parse identifier when resolving test name
    // Instead of just matching on word characters, identifiers should be
    // matched on valid identifier characters
    if (\preg_match(
            '~^(\\\\?(?:\\w+\\\\)*)?((?:^\\w*::)|(?:\\w+::))?(\\w+)$~',
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

        $result = namespace\normalize_identifier($namespace . $class . $function);
    }
    return $result;
}
