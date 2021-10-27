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


final class RunFixture extends struct {
    // @todo Remove $group from RunFixture
    // If we first discovered all tests before running them, we could just keep
    // track of run groups during the discovery process and assign each test to
    // its appropriate group. However, since we immediately run tests after
    // discovering them, we need to keep track of the group during run time so
    // we can assign it to new tests upon discovering them. This is all a bit
    // janky though, because we're potentially "discovering" tests multiple
    // times (once per run) when this could be initially done just once.
    /** @var int */
    public $group;
    /** @var int */
    public $id;
    /** @var string */
    public $name;
    /** @var callable-string */
    public $setup;
    /** @var ?callable-string */
    public $teardown = null;
}


final class DirectoryTest extends struct {
    /** @var string */
    public $name;

    /** @var int */
    public $group;

    /** @var ?callable-string */
    public $setup = null;

    /** @var ?callable-string */
    public $teardown = null;

    /** @var RunFixture[] */
    public $runs = array();

    /** @var array<string, int> */
    public $tests;
}


final class FileTest extends struct {
    /** @var string */
    public $name;
    /** @var int */
    public $group;
    /** @var ?callable-string */
    public $setup;
    /** @var ?callable-string */
    public $teardown;

    /** @var RunFixture[] */
    public $runs;
    /** @var TestInfo[] */
    public $tests;

    /** @var ?callable */
    public $setup_function;
    /** @var ?string */
    public $setup_function_name;
    /** @var ?callable */
    public $teardown_function;
    /** @var ?string */
    public $teardown_function_name;
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

    /** @var ?string */
    public $setup_function;
    /** @var ?string */
    public $teardown_function;
    /** @var string[] */
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
