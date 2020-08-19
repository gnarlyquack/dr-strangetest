<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace test\process_user_targets;

use easytest;
use easytest\Context;
use easytest\Target;


// helper functions

function set_cwd(Context $context, $dir) {
    $cwd = \getcwd();
    $context->teardown(function() use ($cwd) { \chdir($cwd); });
    \chdir($dir);
}


function target_to_array(Target $target) {
    $result = (array)$target;
    foreach ($result['subtargets'] as $key => $value) {
        $result['subtargets'][$key] = target_to_array($value);
    }
    return $result;
}


// helper assertions

function assert_targets(
    Context $context, array $actual, array $actual_errors,
    $expected_root, array $expected_targets, array $expected_errors
) {
    $context->assert_identical(
        $expected_root,
        $actual[0],
        'Wrong test root'
    );

    $targets = $actual[1];
    foreach ($targets as $key => $value) {
        $targets[$key] = namespace\target_to_array($value);
    }
    $context->assert_identical(
        $expected_targets,
        $targets,
        'Incorrect targets'
    );

    $context->assert_identical(
        $expected_errors,
        $actual_errors,
        'Unexpected errors'
    );
}


// tests

function test_uses_cwd_as_default_target(Context $context) {
    $root = __DIR__ . \DIRECTORY_SEPARATOR;

    namespace\set_cwd($context, __DIR__);
    $actual = easytest\process_user_targets(array(), $errors);

    $targets = array($root => array('name' => $root, 'subtargets' => array()));
    namespace\assert_targets($context, $actual, $errors, $root, $targets, array());
}


function test_processes_paths_as_path_targets(Context $context) {
    $root = \sprintf('%1$s%2$stargets%2$s', __DIR__, \DIRECTORY_SEPARATOR);
    $args = array('test1.php', 'test2.php', 'test_dir');

    namespace\set_cwd($context, $root);
    $actual = easytest\process_user_targets($args, $errors);

    $targets = array();
    foreach ($args as $arg) {
        $target = "$root$arg";
        if (\is_dir($target)) {
            $target .= \DIRECTORY_SEPARATOR;
        }
        $targets[$target] = array('name' => $target, 'subtargets' => array());
    }
    namespace\assert_targets($context, $actual, $errors, $root, $targets, array());
}


function test_processes_path_targets(Context $context) {
    $root = \sprintf('%1$s%2$stargets%2$s', __DIR__, \DIRECTORY_SEPARATOR);
    $paths = array('test1.php', 'test2.php', 'test_dir');
    $args = array();
    foreach ($paths as $path) {
        $args[] = "--path=$path";
    }

    namespace\set_cwd($context, $root);
    $actual = easytest\process_user_targets($args, $errors);

    $targets = array();
    foreach ($paths as $path) {
        $target = "$root$path";
        if (\is_dir($target)) {
            $target .= \DIRECTORY_SEPARATOR;
        }
        $targets[$target] = array('name' => $target, 'subtargets' => array());
    }
    namespace\assert_targets($context, $actual, $errors, $root, $targets, array());
}


function test_processes_function_targets(Context $context) {
    $root = \sprintf('%1$s%2$stargets%2$s', __DIR__, \DIRECTORY_SEPARATOR);
    $args = array('test1.php', '--function=foo,bar');

    namespace\set_cwd($context, $root);
    $actual = easytest\process_user_targets($args, $errors);

    $targets = array(
        "{$root}test1.php" => array(
            'name' => "{$root}test1.php",
            'subtargets' => array(
                'function foo' => array(
                    'name' => 'function foo',
                    'subtargets' => array(),
                ),
                'function bar' => array(
                    'name' => 'function bar',
                    'subtargets' => array(),
                ),
            ),
        ),
    );
    namespace\assert_targets($context, $actual, $errors, $root, $targets, array());
}


function test_processes_class_targets(Context $context) {
    $root = \sprintf('%1$s%2$stargets%2$s', __DIR__, \DIRECTORY_SEPARATOR);
    $args = array('test1.php', '--class=foo,bar');

    namespace\set_cwd($context, $root);
    $actual = easytest\process_user_targets($args, $errors);

    $targets = array(
        "{$root}test1.php" => array(
            'name' => "{$root}test1.php",
            'subtargets' => array(
                'class foo' => array(
                    'name' => 'class foo',
                    'subtargets' => array(),
                ),
                'class bar' => array(
                    'name' => 'class bar',
                    'subtargets' => array(),
                ),
            ),
        ),
    );
    namespace\assert_targets($context, $actual, $errors, $root, $targets, array());
}


function test_processes_method_targets(Context $context) {
    $root = \sprintf('%1$s%2$stargets%2$s', __DIR__, \DIRECTORY_SEPARATOR);
    $args = array('test1.php', '--class=foo,bar::one,two');

    namespace\set_cwd($context, $root);
    $actual = easytest\process_user_targets($args, $errors);

    $targets = array(
        "{$root}test1.php" => array(
            'name' => "{$root}test1.php",
            'subtargets' => array(
                'class foo' => array(
                    'name' => 'class foo',
                    'subtargets' => array(),
                ),
                'class bar' => array(
                    'name' => 'class bar',
                    'subtargets' => array(
                        'one' => array(
                            'name' => 'one',
                            'subtargets' => array(),
                        ),
                        'two' => array(
                            'name' => 'two',
                            'subtargets' => array(),
                        ),
                    ),
                ),
            ),
        ),
    );
    namespace\assert_targets($context, $actual, $errors, $root, $targets, array());
}


function test_eliminates_duplicate_function_targets(Context $context) {
    $root = \sprintf('%1$s%2$stargets%2$s', __DIR__, \DIRECTORY_SEPARATOR);
    $args = array('test1.php', '--function=one,two', '--function=two,three');

    namespace\set_cwd($context, $root);
    $actual = easytest\process_user_targets($args, $errors);

    $targets = array(
        "{$root}test1.php" => array(
            'name' => "{$root}test1.php",
            'subtargets' => array(
                'function one' => array(
                    'name' => 'function one',
                    'subtargets' => array(),
                ),
                'function two' => array(
                    'name' => 'function two',
                    'subtargets' => array(),
                ),
                'function three' => array(
                    'name' => 'function three',
                    'subtargets' => array(),
                ),
            ),
        ),
    );
    namespace\assert_targets($context, $actual, $errors, $root, $targets, array());
}


function test_eliminates_duplicate_method_targets(Context $context) {
    $root = \sprintf('%1$s%2$stargets%2$s', __DIR__, \DIRECTORY_SEPARATOR);
    $args = array(
        'test1.php',
        '--class=foo,bar::one,two',
        '--class=foo::one,two',
        '--class=bar::two,three',
    );

    namespace\set_cwd($context, $root);
    $actual = easytest\process_user_targets($args, $errors);

    $targets = array(
        "{$root}test1.php" => array(
            'name' => "{$root}test1.php",
            'subtargets' => array(
                'class foo' => array(
                    'name' => 'class foo',
                    'subtargets' => array(),
                ),
                'class bar' => array(
                    'name' => 'class bar',
                    'subtargets' => array(
                        'one' => array(
                            'name' => 'one',
                            'subtargets' => array(),
                        ),
                        'two' => array(
                            'name' => 'two',
                            'subtargets' => array(),
                        ),
                        'three' => array(
                            'name' => 'three',
                            'subtargets' => array(),
                        ),
                    ),
                ),
            ),
        ),
    );
    namespace\assert_targets($context, $actual, $errors, $root, $targets, array());
}


function test_overrides_method_targets_with_class_target(Context $context) {
    $root = \sprintf('%1$s%2$stargets%2$s', __DIR__, \DIRECTORY_SEPARATOR);
    $args = array(
        'test1.php',
        '--class=foo::one,two',
        '--class=bar::one,two',
        '--class=cat::one,two',
        '--class=foo,bar',
        '--class=cat',
    );

    namespace\set_cwd($context, $root);
    $actual = easytest\process_user_targets($args, $errors);

    $targets = array(
        "{$root}test1.php" => array(
            'name' => "{$root}test1.php",
            'subtargets' => array(
                'class foo' => array(
                    'name' => 'class foo',
                    'subtargets' => array(),
                ),
                'class bar' => array(
                    'name' => 'class bar',
                    'subtargets' => array(),
                ),
                'class cat' => array(
                    'name' => 'class cat',
                    'subtargets' => array(),
                ),
            ),
        ),
    );
    namespace\assert_targets($context, $actual, $errors, $root, $targets, array());
}


function test_eliminates_duplicate_targets_in_file(Context $context) {
    $root = \sprintf('%1$s%2$stargets%2$s', __DIR__, \DIRECTORY_SEPARATOR);
    $args = array(
        'test1.php',
        'test2.php',
        'test1.php',
        '--class=foo,bar::one,two',
        '--function=one,two',
    );

    namespace\set_cwd($context, $root);
    $actual = easytest\process_user_targets($args, $errors);

    $targets = array(
        "{$root}test1.php" => array(
            'name' => "{$root}test1.php",
            'subtargets' => array(),
        ),
        "{$root}test2.php" => array(
            'name' => "{$root}test2.php",
            'subtargets' => array(),
        ),
    );
    namespace\assert_targets($context, $actual, $errors, $root, $targets, array());
}


function test_overrides_targets_in_file_with_file_target(Context $context) {
    $root = \sprintf('%1$s%2$stargets%2$s', __DIR__, \DIRECTORY_SEPARATOR);
    $args = array(
        'test1.php',
        '--class=foo::one,two',
        '--class=bar::one,two',
        '--class=cat::one,two',
        '--class=foo,bar',
        '--class=cat',
    );

    namespace\set_cwd($context, $root);
    $actual = easytest\process_user_targets($args, $errors);

    $targets = array(
        "{$root}test1.php" => array(
            'name' => "{$root}test1.php",
            'subtargets' => array(
                'class foo' => array(
                    'name' => 'class foo',
                    'subtargets' => array(),
                ),
                'class bar' => array(
                    'name' => 'class bar',
                    'subtargets' => array(),
                ),
                'class cat' => array(
                    'name' => 'class cat',
                    'subtargets' => array(),
                ),
            ),
        ),
    );
    namespace\assert_targets($context, $actual, $errors, $root, $targets, array());
}


function test_eliminates_duplicate_path_targets(Context $context) {
    $root = \sprintf('%1$s%2$stargets%2$s', __DIR__, \DIRECTORY_SEPARATOR);
    $args = array(
        'test2.php',
        'test1.php',
        'test_dir/test_subdir',
        'test_dir1',
        'test_dir',
        'test2.php',
        'test_dir1/test2.php',
        'test_dir1/test1.php',
    );

    namespace\set_cwd($context, $root);
    $actual = easytest\process_user_targets($args, $errors);

    $targets = array(
        "{$root}test2.php" => array(
            'name' => "{$root}test2.php",
            'subtargets' => array(),
        ),
        "{$root}test1.php" => array(
            'name' => "{$root}test1.php",
            'subtargets' => array(),
        ),
        "{$root}test_dir1/" => array(
            'name' => "{$root}test_dir1/",
            'subtargets' => array(),
        ),
        "{$root}test_dir/" => array(
            'name' => "{$root}test_dir/",
            'subtargets' => array(),
        ),
    );
    namespace\assert_targets($context, $actual, $errors, $root, $targets, array());
}
