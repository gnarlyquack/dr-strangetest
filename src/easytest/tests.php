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
    public $type;
    public $filename;
    public $namespace;
    public $name;
}


final class DirectoryTest extends struct {
    public $name;
    public $setup;
    public $teardown;
    /** @var ?callable-string */
    public $setup_runs;

    /** @var ?callable-string */
    public $teardown_runs;
    public $tests = array();


    public function find_targets(Logger $logger, $targets) {
        return namespace\find_directory_targets($logger, $this, $targets);
    }


    public function setup(
        BufferingLogger $logger,
        $args = null,
        $run = null
    ) {
        return namespace\run_directory_setup($logger, $this, $args, $run);
    }


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
     * @return array{int, array<?mixed[]>}
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
    public function teardown_runs(
        BufferingLogger $logger,
        $args = null,
        $run_name = null
    ) {
        namespace\run_directory_teardown_runs($logger, $this, $args, $run_name);
    }


    public function run(
        State $state, BufferingLogger $logger,
        array $args = null, array $run = null, array $targets = null
    ) {
        namespace\run_directory_tests($state, $logger, $this, $args, $run, $targets);
    }
}


final class FileTest extends struct {
    public $name;
    public $setup;
    public $teardown;
    /** @var ?callable-string */
    public $setup_runs;

    /** @var ?callable-string */
    public $teardown_runs;
    public $tests = array();

    public $setup_function;
    public $setup_function_name;
    public $teardown_function;
    public $teardown_function_name;


    public function find_targets(Logger $logger, $targets) {
        return namespace\find_file_targets($logger, $this, $targets);
    }


    public function setup(
        BufferingLogger $logger,
        array $args = null,
        $run = null
    ) {
        return namespace\run_file_setup($logger, $this, $args, $run);
    }


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
     * @return array{int, array<?mixed[]>}
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
    public function teardown_runs(
        BufferingLogger $logger,
        $args = null,
        $run_name = null
    ) {
        namespace\run_file_teardown_runs($logger, $this, $args, $run_name);
    }


    public function run(
        State $state, BufferingLogger $logger,
        array $args = null, array $run = null, array $targets = null
    ) {
        namespace\run_file_tests($state, $logger, $this, $args, $run, $targets);
    }
}


final class ClassTest extends struct {
    public $file;
    public $namespace;

    public $name;
    public $object;
    public $setup;
    public $teardown;

    public $setup_function;
    public $teardown_function;
    public $tests = array();


    public function find_targets(Logger $logger, $targets) {
        return namespace\find_class_targets($logger, $this, $targets);
    }


    public function setup(
        BufferingLogger $logger,
        array $args = null,
        $run = null
    ) {
        return namespace\run_class_setup($logger, $this, $args, $run);
    }


    public function teardown(
        State $state,
        BufferingLogger $logger,
        array $args = null,
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
     * @return array{int, array<?mixed[]>}
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
     * @return void
     */
    public function teardown_runs() {}


    public function run(
        State $state, BufferingLogger $logger,
        array $args = null, array $run = null, array $targets = null
    ) {
        namespace\run_class_tests($state, $logger, $this, $args, $run, $targets);
    }
}


final class FunctionTest extends struct {
    public $file;
    public $namespace;
    public $class;
    public $function;

    public $name;
    public $setup;
    public $teardown;
    public $test;
    public $result;

    public $setup_name;
    public $teardown_name;


    public function setup(
        BufferingLogger $logger,
        array $args = null,
        $run = null
    ) {
        return namespace\run_function_setup($logger, $this, $args, $run);
    }


    public function teardown(
        State $state,
        BufferingLogger $logger,
        array $args = null,
        $run = null
    ) {
        namespace\run_function_teardown($state, $logger, $this, $args, $run);
    }


    /**
     * @param BufferingLogger $logger
     * @param ?mixed[] $args
     * @param ?string $run_name
     * @param ?bool $update_run
     * @return array{int, array<?mixed[]>}
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
     * @return void
     */
    public function teardown_runs() {}


    public function run(
        State $state, BufferingLogger $logger,
        array $args = null, array $run = null, array $targets = null
    ) {
        namespace\run_function_test($state, $logger, $this, $args, $run, $targets);
    }
}
