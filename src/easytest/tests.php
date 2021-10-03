<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace easytest;


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


interface Test {
    /**
     * @param Target[] $targets
     * @return array{bool, Target[]}
     */
    public function find_targets(Logger $logger, $targets);

    /**
     * @param ?mixed[] $args
     * @param ?string $run
     * @return array{int, mixed}
     */
    public function setup(BufferingLogger $logger, array $args = null, $run = null);

    /**
     * @param ?mixed[] $args
     * @param ?string $run
     * @return void
     */
    public function teardown(
        State $state,
        BufferingLogger $logger,
        $args = null,
        $run = null);

    /**
     * @param ?bool $update_run
     * @param ?string $run_name
     * @param ?mixed[] $args
     * @return array{int, mixed}
     */
    public function setup_runs(
        BufferingLogger $logger,
        &$update_run,
        $run_name,
        $args = null);

    /**
     * @param mixed $args
     * @param ?string $run_name
     * @return void
     */
    public function teardown_runs(BufferingLogger $logger, $args = null, $run_name = null);

    /**
     * @param ?mixed[] $args
     * @param ?string[] $run
     * @param ?Target[] $targets
     * @return void
     */
    public function run(
        State $state, BufferingLogger $logger,
        array $args = null, array $run = null, array $targets = null);

    /**
     * @return string
     */
    public function name();

    /**
     * @return ?string
     */
    public function setup_name();

    /**
     * @return ?string
     */
    public function setup_runs_name();
}


final class DirectoryTest extends struct implements Test {
    /** @var string */
    public $name;
    /** @var ?callable-string */
    public $setup;
    /** @var ?callable-string */
    public $teardown;
    /** @var ?callable-string */
    public $setup_runs;

    /** @var ?callable-string */
    public $teardown_runs;
    /** @var array<string, int> */
    public $tests = array();


    /**
     * @param Target[] $targets
     * @return array{bool, Target[]}
     */
    public function find_targets(Logger $logger, $targets) {
        return namespace\find_directory_targets($logger, $this, $targets);
    }


    /**
     * @param ?mixed[] $args
     * @param ?string $run
     * @return array{int, mixed}
     */
    public function setup(BufferingLogger $logger, array $args = null, $run = null) {
        return namespace\run_directory_setup($logger, $this, $args, $run);
    }


    /**
     * @param ?mixed[] $args
     * @param ?string $run
     * @return void
     */
    public function teardown(
        State $state,
        BufferingLogger $logger,
        $args = null,
        $run = null
    ) {
        namespace\run_directory_teardown($logger, $this, $args, $run);
    }


    /**
     * @param BufferingLogger $logger
     * @param ?mixed[] $args
     * @param ?string $run_name
     * @param ?bool $update_run
     * @return array{int, mixed}
     */
    public function setup_runs(
        BufferingLogger $logger,
        &$update_run,
        $run_name,
        $args = null
    ) {
        return namespace\run_directory_setup_runs($logger, $this, $update_run, $run_name, $args);
    }


    /**
     * @param BufferingLogger $logger
     * @param mixed $args
     * @param ?string $run_name
     * @return void
     */
    public function teardown_runs(BufferingLogger $logger, $args = null, $run_name = null) {
        namespace\run_directory_teardown_runs($logger, $this, $args, $run_name);
    }


    /**
     * @param ?mixed[] $args
     * @param ?string[] $run
     * @param ?Target[] $targets
     * @return void
     */
    public function run(
        State $state, BufferingLogger $logger,
        array $args = null, array $run = null, array $targets = null
    ) {
        namespace\run_directory_tests($state, $logger, $this, $args, $run, $targets);
    }

    /**
     * @return string
     */
    public function name() {
        return $this->name;
    }

    /**
     * @return ?string
     */
    public function setup_name() {
        return $this->setup;
    }

    /**
     * @return ?string
     */
    public function setup_runs_name() {
        return $this->setup_runs;
    }
}


final class FileTest extends struct implements Test {
    /** @var string */
    public $name;
    /** @var ?callable-string */
    public $setup;
    /** @var ?callable-string */
    public $teardown;
    /** @var ?callable-string */
    public $setup_runs;

    /** @var ?callable-string */
    public $teardown_runs;
    /** @var TestInfo[] */
    public $tests = array();

    /** @var ?callable */
    public $setup_function;
    /** @var string */
    public $setup_function_name;
    /** @var ?callable */
    public $teardown_function;
    /** @var string */
    public $teardown_function_name;


    /**
     * @param Target[] $targets
     * @return array{bool, Target[]}
     */
    public function find_targets(Logger $logger, $targets) {
        return namespace\find_file_targets($logger, $this, $targets);
    }


    /**
     * @param ?mixed[] $args
     * @param ?string $run
     * @return array{int, mixed}
     */
    public function setup(BufferingLogger $logger, array $args = null, $run = null) {
        return namespace\run_file_setup($logger, $this, $args, $run);
    }


    /**
     * @param ?mixed[] $args
     * @param ?string $run
     * @return void
     */
    public function teardown(
        State $state,
        BufferingLogger $logger,
        $args = null,
        $run = null
    ) {
        namespace\run_file_teardown($logger, $this, $args, $run);
    }


    /**
     * @param BufferingLogger $logger
     * @param ?mixed[] $args
     * @param ?string $run_name
     * @param ?bool $update_run
     * @return array{int, mixed}
     */
    public function setup_runs(
        BufferingLogger $logger,
        &$update_run,
        $run_name,
        $args = null
    ) {
        return namespace\run_file_setup_runs($logger, $this, $update_run, $run_name, $args);
    }


    /**
     * @param BufferingLogger $logger
     * @param ?mixed $args
     * @param ?string $run_name
     * @return void
     */
    public function teardown_runs(BufferingLogger $logger, $args = null, $run_name = null) {
        namespace\run_file_teardown_runs($logger, $this, $args, $run_name);
    }


    /**
     * @param ?mixed[] $args
     * @param ?string[] $run
     * @param ?Target[] $targets
     * @return void
     */
    public function run(
        State $state, BufferingLogger $logger,
        array $args = null, array $run = null, array $targets = null
    ) {
        namespace\run_file_tests($state, $logger, $this, $args, $run, $targets);
    }

    /**
     * @return string
     */
    public function name() {
        return $this->name;
    }

    /**
     * @return ?string
     */
    public function setup_name() {
        return $this->setup;
    }

    /**
     * @return ?string
     */
    public function setup_runs_name() {
        return $this->setup_runs;
    }
}


final class ClassTest extends struct implements Test {
    /** @var string */
    public $file;
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


    /**
     * @param Target[] $targets
     * @return array{bool, string[]}
     */
    public function find_targets(Logger $logger, $targets) {
        return namespace\find_class_targets($logger, $this, $targets);
    }


    /**
     * @param ?mixed[] $args
     * @param ?string $run
     * @return array{int, mixed}
     */
    public function setup(BufferingLogger $logger, array $args = null, $run = null) {
        return namespace\run_class_setup($logger, $this, $args, $run);
    }


    /**
     * @param ?mixed[] $args
     * @param ?string $run
     * @return void
     */
    public function teardown(
        State $state,
        BufferingLogger $logger,
        $args = null,
        $run = null
    ) {
        \assert(!$args);
        namespace\run_class_teardown($logger, $this, $run);
    }


    /**
     * @param BufferingLogger $logger
     * @param ?mixed[] $args
     * @param ?string $run_name
     * @param ?bool $update_run
     * @return array{int, mixed}
     */
    public function setup_runs(
        BufferingLogger $logger,
        &$update_run,
        $run_name,
        $args = null
    ) {
        $update_run = false;
        return array(namespace\RESULT_PASS, array($args));
    }


    /**
     * @param mixed $args
     * @param ?string $run_name
     * @return void
     */
    public function teardown_runs(BufferingLogger $logger, $args = null, $run_name = null) {}


    /**
     * @param ?mixed[] $args
     * @param ?string[] $run
     * @param ?Target[] $targets
     * @return void
     */
    public function run(
        State $state, BufferingLogger $logger,
        array $args = null, array $run = null, array $targets = null
    ) {
        namespace\run_class_tests($state, $logger, $this, $args, $run, $targets);
    }

    /**
     * @return string
     */
    public function name() {
        return $this->name;
    }

    /**
     * @return ?string
     */
    public function setup_name() {
        return $this->setup;
    }

    /**
     * @return ?string
     */
    public function setup_runs_name() {
        throw new InvalidCodePath('ClassTest cannot setup runs');
    }
}


final class FunctionTest extends struct implements Test {
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


    /**
     * @param Target[] $targets
     * @return array{bool, Target[]}
     */
    public function find_targets(Logger $logger, $targets) {
        throw new InvalidCodePath('Should not be trying to find targets for function test');
    }


    /**
     * @param ?mixed[] $args
     * @param ?string $run
     * @return array{int, mixed}
     */
    public function setup(BufferingLogger $logger, array $args = null, $run = null) {
        return namespace\run_function_setup($logger, $this, $args, $run);
    }


    /**
     * @param ?mixed[] $args
     * @param ?string $run
     * @return void
     */
    public function teardown(
        State $state,
        BufferingLogger $logger,
        $args = null,
        $run = null
    ) {
        namespace\run_function_teardown($state, $logger, $this, $args, $run);
    }


    /**
     * @param BufferingLogger $logger
     * @param ?mixed[] $args
     * @param ?string $run_name
     * @param ?bool $update_run
     * @return array{int, mixed}
     */
    public function setup_runs(
        BufferingLogger $logger,
        &$update_run,
        $run_name,
        $args = null
    ) {
        $update_run = false;
        return array(namespace\RESULT_PASS, array($args));
    }


    /**
     * @param mixed $args
     * @param ?string $run_name
     * @return void
     */
    public function teardown_runs(BufferingLogger $logger, $args = null, $run_name = null) {}


    /**
     * @param ?mixed[] $args
     * @param ?string[] $run
     * @param ?Target[] $targets
     * @return void
     */
    public function run(
        State $state, BufferingLogger $logger,
        array $args = null, array $run = null, array $targets = null
    ) {
        namespace\run_function_test($state, $logger, $this, $args, $run, $targets);
    }

    /**
     * @return string
     */
    public function name() {
        return $this->name;
    }

    /**
     * @return ?string
     */
    public function setup_name() {
        return $this->setup_name;
    }

    /**
     * @return ?string
     */
    public function setup_runs_name() {
        throw new InvalidCodePath('FunctionTest cannot setup runs');
    }
}
