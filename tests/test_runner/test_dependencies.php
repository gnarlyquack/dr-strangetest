<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.


class TestDependencies {
    private $logger;
    private $path;


    public function setup() {
        $this->path = __DIR__ . '/sample_files/dependencies/';
        $this->logger = new easytest\BasicLogger(easytest\LOG_ALL);
    }


    // helper assertions

    private function assert_events($expected) {
        $paths = $this->path;
        if (!\is_array($paths)) {
            $paths = array($paths);
        }
        $targets = array();
        foreach ($paths as $path) {
            if (!($path instanceof easytest\Target)) {
                $target = new easytest\Target();
                $target->name = $path;
                $targets[] = $target;
            }
        }
        easytest\discover_tests(
            new easytest\BufferingLogger($this->logger),
            $targets
        );
        assert_events($expected, $this->logger);
    }


    // tests

    public function test_tests_are_run_in_order_of_dependencies() {
        $this->path .= 'depends_pass/test.php';

        $this->assert_events(array(
            array(easytest\EVENT_PASS, 'depends_pass\\test_one', null),
            array(easytest\EVENT_PASS, 'depends_pass\\test::test_one', null),

            array(easytest\EVENT_PASS, 'depends_pass\\test_two', null),
            array(easytest\EVENT_PASS, 'depends_pass\\test_three', null),

            array(easytest\EVENT_PASS, 'depends_pass\\test::test_two', null),
            array(easytest\EVENT_PASS, 'depends_pass\\test::test_three', null),
        ));
    }


    public function test_tests_are_skipped_if_depending_on_a_failed_test() {
        $this->path .= 'depends_fail/test.php';

        $this->assert_events(array(
            array(easytest\EVENT_FAIL, 'depends_fail\\test_one', 'I fail'),
            array(easytest\EVENT_FAIL, 'depends_fail\\test::test_one', 'I fail'),

            array(easytest\EVENT_SKIP, 'depends_fail\\test_two', "This test depends on 'depends_fail\\test_one', which did not pass"),
            array(easytest\EVENT_SKIP, 'depends_fail\\test_three', "This test depends on 'depends_fail\\test_two', which did not pass"),

            array(easytest\EVENT_SKIP, 'depends_fail\\test::test_two', "This test depends on 'depends_fail\\test::test_one', which did not pass"),
            array(easytest\EVENT_SKIP, 'depends_fail\\test::test_three', "This test depends on 'depends_fail\\test::test_two', which did not pass"),
        ));
    }


    public function test_dependencies_resolve_to_correct_test_run() {
        $this->path .= 'depends_params/test.php';

        $this->assert_events(array(
            array(easytest\EVENT_PASS, 'depends_params\\test_one (0)', null),
            array(easytest\EVENT_PASS, 'depends_params\\test_one (1)', null),
            array(easytest\EVENT_PASS, 'depends_params\\test::test_one (0)', null),
            array(easytest\EVENT_PASS, 'depends_params\\test::test_one (1)', null),

            array(
                easytest\EVENT_FAIL, 'depends_params\\test_two (0)',
                "Assertion \"\$expected === \$actual\" failed\n\n- \$expected\n+ \$actual\n\n- 2\n+ 1"
            ),
            array(easytest\EVENT_PASS, 'depends_params\\test_two (1)', null),

            array(
                easytest\EVENT_SKIP, 'depends_params\\test_three (0)',
                "This test depends on 'depends_params\\test_two (0)', which did not pass"
            ),
            array(easytest\EVENT_PASS, 'depends_params\\test_three (1)', null),

            array(
                easytest\EVENT_FAIL, 'depends_params\\test::test_two (0)',
                "Assertion \"\$expected === \$actual\" failed\n\n- \$expected\n+ \$actual\n\n- 2\n+ 1"
            ),
            array(
                easytest\EVENT_SKIP, 'depends_params\\test::test_three (0)',
                "This test depends on 'depends_params\\test::test_two (0)', which did not pass"
            ),

            array(easytest\EVENT_PASS, 'depends_params\\test::test_two (1)', null),
            array(easytest\EVENT_PASS, 'depends_params\\test::test_three (1)', null),
        ));
    }


    public function test_dependee_tests_that_are_never_run_is_an_error() {
        $this->path .= 'unrun_depends/test.php';

        $this->assert_events(array(
            array(easytest\EVENT_SKIP, 'unrun_depends\\test_five', 'Skip me'),
            array(easytest\EVENT_SKIP, 'unrun_depends\\test_six', "This test depends on 'unrun_depends\\test_five', which did not pass"),
            array(easytest\EVENT_SKIP, 'unrun_depends\\test1::setup_object', 'Skip me'),
            array(easytest\EVENT_SKIP, 'unrun_depends\\test2::test_five', 'Skip me'),
            array(easytest\EVENT_SKIP, 'unrun_depends\\test2::test_six', "This test depends on 'unrun_depends\\test2::test_five', which did not pass"),

            array(easytest\EVENT_ERROR, 'foobar', 'Other tests depend on this test, but this test was never run'),
            array(easytest\EVENT_SKIP, 'unrun_depends\\test_one', "This test depends on 'foobar', which did not pass"),
            array(easytest\EVENT_SKIP, 'unrun_depends\\test_two', "This test depends on 'unrun_depends\\test_one', which did not pass"),

            array(easytest\EVENT_ERROR, 'unrun_depends\\test1::test_one', 'Other tests depend on this test, but this test was never run'),
            array(easytest\EVENT_SKIP, 'unrun_depends\\test_three', "This test depends on 'unrun_depends\\test1::test_one', which did not pass"),
            array(easytest\EVENT_SKIP, 'unrun_depends\\test_four', "This test depends on 'unrun_depends\\test_three', which did not pass"),

            array(easytest\EVENT_ERROR, 'frobitz', 'Other tests depend on this test, but this test was never run'),
            array(easytest\EVENT_SKIP, 'unrun_depends\\test2::test_one', "This test depends on 'frobitz', which did not pass"),
            array(easytest\EVENT_SKIP, 'unrun_depends\\test2::test_two', "This test depends on 'unrun_depends\\test2::test_one', which did not pass"),

            array(easytest\EVENT_SKIP, 'unrun_depends\\test2::test_three', "This test depends on 'unrun_depends\\test1::test_one', which did not pass"),
            array(easytest\EVENT_SKIP, 'unrun_depends\\test2::test_four', "This test depends on 'unrun_depends\\test2::test_three', which did not pass"),
        ));
    }


    public function test_cyclical_dependencies_are_an_error() {
        $this->path .= 'cyclical_depends/test.php';


        $this->assert_events(array(
            array(easytest\EVENT_ERROR, 'cyclical_depends\\test_five', "This test has a cyclical dependency with the following tests:\n\tcyclical_depends\\test_four\n\tcyclical_depends\\test_three"),
            array(easytest\EVENT_SKIP, 'cyclical_depends\\test_four', "This test depends on 'cyclical_depends\\test_five', which did not pass"),
            array(easytest\EVENT_SKIP, 'cyclical_depends\\test_three', "This test depends on 'cyclical_depends\\test_four', which did not pass"),
            array(easytest\EVENT_SKIP, 'cyclical_depends\\test_two', "This test depends on 'cyclical_depends\\test_three', which did not pass"),
            array(easytest\EVENT_SKIP, 'cyclical_depends\\test_one', "This test depends on 'cyclical_depends\\test_two', which did not pass"),

            array(easytest\EVENT_ERROR, 'cyclical_depends\\test::test_five', "This test has a cyclical dependency with the following tests:\n\tcyclical_depends\\test::test_four\n\tcyclical_depends\\test::test_three"),
            array(easytest\EVENT_SKIP, 'cyclical_depends\\test::test_four', "This test depends on 'cyclical_depends\\test::test_five', which did not pass"),
            array(easytest\EVENT_SKIP, 'cyclical_depends\\test::test_three', "This test depends on 'cyclical_depends\\test::test_four', which did not pass"),
            array(easytest\EVENT_SKIP, 'cyclical_depends\\test::test_two', "This test depends on 'cyclical_depends\\test::test_three', which did not pass"),
            array(easytest\EVENT_SKIP, 'cyclical_depends\\test::test_one', "This test depends on 'cyclical_depends\\test::test_two', which did not pass"),
        ));
    }


    public function test_multiple_dependencies_can_be_declared() {
        $this->path .= 'multiple_depends/test.php';

        $this->assert_events(array(
            array(easytest\EVENT_PASS, 'multiple_depends\\test_seven', null),
            array(easytest\EVENT_PASS, 'multiple_depends\\test_ten', null),

            array(easytest\EVENT_OUTPUT, 'multiple_depends\\test', '.'),
            array(easytest\EVENT_PASS, 'multiple_depends\\test::test_six', null),
            array(easytest\EVENT_FAIL, 'multiple_depends\\test::test_eight', 'I fail'),
            array(easytest\EVENT_PASS, 'multiple_depends\\test::test_nine', null),

            array(easytest\EVENT_PASS, 'multiple_depends\\test_five', null),

            array(easytest\EVENT_OUTPUT, 'multiple_depends\\test', '.'),
            array(easytest\EVENT_PASS, 'multiple_depends\\test::test_two', null),

            array(easytest\EVENT_PASS, 'multiple_depends\\test_three', null),

            array(easytest\EVENT_OUTPUT, 'multiple_depends\\test', '.'),
            array(easytest\EVENT_SKIP, 'multiple_depends\\test::test_four', "This test depends on 'multiple_depends\\test::test_eight', which did not pass"),

            array(easytest\EVENT_SKIP, 'multiple_depends\\test_one', "This test depends on 'multiple_depends\\test::test_four', which did not pass"),
        ));
    }


    public function test_multiple_dependencies_can_be_declared_separately() {
        $this->path .= 'separate_depends/test.php';

        $this->assert_events(array(
            array(easytest\EVENT_PASS, 'separate_depends\\test_seven', null),
            array(easytest\EVENT_PASS, 'separate_depends\\test_ten', null),

            array(easytest\EVENT_OUTPUT, 'separate_depends\\test', '.'),
            array(easytest\EVENT_PASS, 'separate_depends\\test::test_six', null),
            array(easytest\EVENT_FAIL, 'separate_depends\\test::test_eight', 'I fail'),
            array(easytest\EVENT_PASS, 'separate_depends\\test::test_nine', null),

            array(easytest\EVENT_PASS, 'separate_depends\\test_five', null),

            array(easytest\EVENT_OUTPUT, 'separate_depends\\test', '.'),
            array(easytest\EVENT_PASS, 'separate_depends\\test::test_two', null),

            array(easytest\EVENT_PASS, 'separate_depends\\test_three', null),

            array(easytest\EVENT_OUTPUT, 'separate_depends\\test', '.'),
            array(easytest\EVENT_SKIP, 'separate_depends\\test::test_four', "This test depends on 'separate_depends\\test::test_eight', which did not pass"),

            array(easytest\EVENT_SKIP, 'separate_depends\\test_one', "This test depends on 'separate_depends\\test::test_four', which did not pass"),
        ));
    }


    public function test_dependencies_between_parameterized_tests() {
        $path = $this->path;
        $paths = array(
            "{$path}param_xdepends/test_nonparam.php",
            "{$path}param_xdepends/test_param.php",
        );
        $this->path = $paths;

        $this->assert_events(array(
            array(easytest\EVENT_PASS, 'param_xdepend\\nonparam\\test_one', null),
            array(easytest\EVENT_FAIL, 'param_xdepend\\nonparam\\test_six', 'I fail'),

            array(easytest\EVENT_SKIP, 'param_xdepend\\param\\test_five (0)', "This test depends on 'param_xdepend\\nonparam\\test_six', which did not pass"),
            array(easytest\EVENT_SKIP, 'param_xdepend\\param\\test_five (1)', "This test depends on 'param_xdepend\\nonparam\\test_six', which did not pass"),
            array(easytest\EVENT_PASS, 'param_xdepend\\param\\test_one (0)', null),
            array(easytest\EVENT_PASS, 'param_xdepend\\param\\test_one (1)', null),
            array(easytest\EVENT_PASS, 'param_xdepend\\param\\test_two (0)', null),
            array(easytest\EVENT_PASS, 'param_xdepend\\param\\test_two (1)', null),
            array(easytest\EVENT_PASS, 'param_xdepend\\nonparam\\test_two', null),
            array(
                easytest\EVENT_FAIL,
                'param_xdepend\\param\\test_three (0)',
                "Assertion \"\$expected === \$actual\" failed\n\n- \$expected\n+ \$actual\n\n- 14\n+ 10",
            ),
            array(easytest\EVENT_PASS, 'param_xdepend\\param\\test_three (1)', null),
            array(easytest\EVENT_SKIP, 'param_xdepend\\param\\test_four (0)', "This test depends on 'param_xdepend\\param\\test_three (0)', which did not pass"),
            array(easytest\EVENT_PASS, 'param_xdepend\\param\\test_four (1)', null),

            array(easytest\EVENT_SKIP, 'param_xdepend\\nonparam\\test_three', "This test depends on 'param_xdepend\\param\\test_four', which did not pass"),
            array(easytest\EVENT_SKIP, 'param_xdepend\\nonparam\\test_four', "This test depends on 'param_xdepend\\param\\test_four (0)', which did not pass"),
            array(easytest\EVENT_PASS, 'param_xdepend\\nonparam\\test_five', null),
            array(easytest\EVENT_SKIP, 'param_xdepend\\param\\test_six (0)', "This test depends on 'param_xdepend\\param\\test_five (0)', which did not pass"),
            array(easytest\EVENT_SKIP, 'param_xdepend\\param\\test_six (1)', "This test depends on 'param_xdepend\\param\\test_five (1)', which did not pass"),
        ));
    }


    public function test_an_error_supersedes_dependencies() {
        $this->path .= 'error_depend/test.php';

        $this->assert_events(array(
            array(easytest\EVENT_ERROR, 'teardown_function for error_depend\\test_one', 'I erred'),
            array(easytest\EVENT_PASS, 'error_depend\\test_two', null),

            array(easytest\EVENT_ERROR, 'teardown for error_depend\\test::test_one', 'I erred'),
            array(easytest\EVENT_PASS, 'error_depend\\test::test_two', null),
        ));
    }
}
