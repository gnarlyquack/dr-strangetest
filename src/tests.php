<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace strangetest;


final class NamespaceInfo extends struct
{
    /** @var string */
    public $name;

    /** @var array<string, string> */
    public $use;

    /** @var array<string, string> */
    public $use_function;


    /**
     * @param string $name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }
}


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

    /** @var NamespaceInfo */
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
    /** @var int Unique framework-generated run group id */
    public $id;

    /** @var string The test file or directory which declared this run group */
    public $filepath;

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

    /** @var array<string, NamespaceInfo> */
    public $namespaces = array();

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

    /** @var NamespaceInfo */
    public $namespace;

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
