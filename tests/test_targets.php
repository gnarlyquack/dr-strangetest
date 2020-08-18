<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace test\targets;

use easytest;
use easytest\Context;
use easytest\Target;


function set_cwd(Context $context, $dir) {
    $cwd = \getcwd();
    $context->teardown(function() use ($cwd) { \chdir($cwd); });
    \chdir($dir);
}


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


function target_to_array(Target $target) {
    $result = (array)$target;
    foreach ($result['subtargets'] as $key => $value) {
        $result['subtargets'][$key] = target_to_array($value);
    }
    return $result;
}


function test_target_defaults_to_cwd(Context $context) {
    $root = __DIR__ . \DIRECTORY_SEPARATOR;

    namespace\set_cwd($context, __DIR__);
    $actual = easytest\process_user_targets(array(), $errors);

    $targets = array($root => array('name' => $root, 'subtargets' => array()));
    namespace\assert_targets($context, $actual, $errors, $root, $targets, array());
}


function test_processes_paths(Context $context) {
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


function test_processes_path_spec(Context $context) {
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


function test_processes_function_spec(Context $context) {
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


function test_processes_class_spec(Context $context) {
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


function test_processes_method_spec(Context $context) {
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
