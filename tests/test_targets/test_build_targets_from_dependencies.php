<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace test\build_targets_from_dependencies;

use easytest;
use easytest\Dependency;
use easytest\Target;


// helper functions

function targets_to_array(array $targets) {
    foreach ($targets as $key => $target) {
        $targets[$key] = target_to_array($target);
    }
    return $targets;
}

function target_to_array(Target $target) {
    $result = (array)$target;
    foreach ($result['subtargets'] as $key => $value) {
        $result['subtargets'][$key] = target_to_array($value);
    }
    return $result;
}



function test_builds_targets() {
    $dependencies = array(
        new Dependency('file1.php', null, 'function1'),
        new Dependency('file1.php', null, 'function2'),
        new Dependency('file2.php', 'class1', 'method1'),
        new Dependency('file2.php', 'class1', 'method2'),
        new Dependency('file2.php', 'class2', 'method1'),
        new Dependency('file2.php', 'class2', 'method2'),
        new Dependency('file2.php', 'class1', 'method3'),
        new Dependency('file2.php', 'class1', 'method4'),
        new Dependency('file3.php', 'class1', 'method1'),
        new Dependency('file3.php', null, 'function1'),
        new Dependency('file3.php', 'class1', 'method2'),
        new Dependency('file3.php', null, 'function2'),
        new Dependency('file1.php', null, 'function3'),
        new Dependency('file1.php', null, 'function4'),
        new Dependency('file3.php', 'class1', 'method3'),
    );

    $expected = array(
        array(
            'name' => 'file1.php',
            'subtargets' => array(
                array('name' => 'function function1', 'subtargets' => array()),
                array('name' => 'function function2', 'subtargets' => array()),
            ),
        ),
        array(
            'name' => 'file2.php',
            'subtargets' => array(
                array(
                    'name' => 'class class1',
                    'subtargets' => array(
                        array('name' => 'method1', 'subtargets' => array()),
                        array('name' => 'method2', 'subtargets' => array()),
                    ),
                ),
                array(
                    'name' => 'class class2',
                    'subtargets' => array(
                        array('name' => 'method1', 'subtargets' => array()),
                        array('name' => 'method2', 'subtargets' => array()),
                    ),
                ),
                array(
                    'name' => 'class class1',
                    'subtargets' => array(
                        array('name' => 'method3', 'subtargets' => array()),
                        array('name' => 'method4', 'subtargets' => array()),
                    ),
                ),
            ),
        ),
        array(
            'name' => 'file3.php',
            'subtargets' => array(
                array(
                    'name' => 'class class1',
                    'subtargets' => array(
                        array('name' => 'method1', 'subtargets' => array()),
                    ),
                ),
                array(
                    'name' => 'function function1',
                    'subtargets' => array(),
                ),
                array(
                    'name' => 'class class1',
                    'subtargets' => array(
                        array('name' => 'method2', 'subtargets' => array()),
                    ),
                ),
                array(
                    'name' => 'function function2',
                    'subtargets' => array(),
                ),
            ),
        ),
        array(
            'name' => 'file1.php',
            'subtargets' => array(
                array('name' => 'function function3', 'subtargets' => array()),
                array('name' => 'function function4', 'subtargets' => array()),
            ),
        ),
        array(
            'name' => 'file3.php',
            'subtargets' => array(
                array(
                    'name' => 'class class1',
                    'subtargets' => array(
                        array('name' => 'method3', 'subtargets' => array()),
                    ),
                ),
            ),
        ),
    );
    $actual = easytest\build_targets_from_dependencies($dependencies);
    easytest\assert_identical(
        $expected,
        namespace\targets_to_array($actual)
    );

}
