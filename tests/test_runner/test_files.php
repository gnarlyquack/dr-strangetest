<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.


class TestFiles {
    private $logger;
    private $path;


    public function setup() {
        $this->logger = new easytest\BasicLogger(easytest\LOG_ALL);
        $this->path = __DIR__ . '/sample_files/files/';
    }


    // helper assertions

    public function assert_log($expected) {
        $actual = $this->logger->get_log()->get_events();
        foreach ($actual as $i => $event) {
            list($type, $source, $reason) = $event;
            // #BC(5.6): Check if reason is instance of Exception
            if ($reason instanceof \Throwable
                || $reason instanceof \Exception)
            {
                $reason = $reason->getMessage();
            }

            $actual[$i] = array($type, $source, $reason);
        }
        easytest\assert_identical($expected, $actual);
    }


    // tests

    public function test_discover_file() {
        $path = $this->path . 'TestMyFile.php';
        easytest\discover_tests($this->logger, array($path));

        $this->assert_log(array(
            array(
                easytest\EVENT_OUTPUT,
                $path,
                "class TestTextBefore {}\n\n\nclass TestTestAfter {}\n"
            ),
            array(easytest\EVENT_PASS, 'Test1::test_me', null),
            array(easytest\EVENT_PASS, 'test_two::test1', null),
            array(easytest\EVENT_PASS, 'test_two::test2', null),
            array(easytest\EVENT_PASS, 'test_two::test3', null),
            array(easytest\EVENT_PASS, 'Test3::test_two', null),
        ));
    }


    /*
    public function test_handles_error_in_test_file() {
        $path = $this->path . 'test_file_error';
        easytest\discover_tests($this->logger, array($path));

        // Note that any exception thrown while including a file, including a
        // skip, is reported as an error
        $expected = array(
            "$path/" => array(
                'fixtures' => array(
                    'setup_directory_test_file_error',
                    'teardown_directory_test_file_error',
                ),
                'tests' => array(
                    "$path/test1.php" => array(easytest\EVENT_ERROR, 'An error happened'),
                    "test_file_error_two::test" => array(easytest\EVENT_PASS, null),
                    "$path/test3.php" => array(easytest\EVENT_ERROR, 'Skip me'),
                ),
            ),
        );
        $this->assert_events($expected, $context);
    }
     */


    public function test_nonexistent_path() {
        $path = $this->path . 'foobar.php';
        easytest\discover_tests($this->logger, array($path));

        $this->assert_log(array(
            array(
                easytest\EVENT_ERROR,
                $path,
                'No such file or directory'
            ),
        ));
    }


    public function test_simple_namespaces() {
        $path = $this->path . 'test_simple_namespaces.php';
        easytest\discover_tests($this->logger, array($path));

        $this->assert_log(array(
            array(easytest\EVENT_PASS, 'ns02\\TestNamespaces::test', null),
            array(easytest\EVENT_PASS, 'ns03\\TestNamespaces::test', null),
        ));
    }


    public function test_bracketed_namespaces() {
        $path = $this->path . 'test_bracketed_namespaces.php';
        easytest\discover_tests($this->logger, array($path));

        $this->assert_log(array(
            array(easytest\EVENT_PASS, 'ns01\\ns1\\TestNamespaces::test', null),
            array(easytest\EVENT_PASS, 'ns01\\ns2\\TestNamespaces::test', null),
            array(easytest\EVENT_PASS, 'TestNamespaces::test', null),
        ));
    }


    public function test_output_buffering() {
        $path = $this->path . 'test_file_buffering.php';
        easytest\discover_tests($this->logger, array($path));

        $this->assert_log(array(
            array(easytest\EVENT_OUTPUT, $path, $path),
            array(easytest\EVENT_OUTPUT, 'test_file_buffering', '__construct'),
            array(easytest\EVENT_PASS, 'test_file_buffering::test', null),
        ));
    }


    public function test_does_not_discover_anonymous_classes() {
        // #BC(5.6): Check if anonymous classes are supported
        if (version_compare(PHP_VERSION, '7.0', '<')) {
            easytest\skip('PHP 7 introduced anonymous classes');
        }

        $path = $this->path . 'TestAnonymousClass.php';
        easytest\discover_tests($this->logger, array($path));

        $this->assert_log(array(
            array(easytest\EVENT_PASS, 'TestAnonymousClass::test', null),
        ));
    }


    public function test_does_not_find_conditionally_nondeclared_tests() {
        $paths = array();
        $paths[] = $this->path . 'test_conditional_a.php';
        $paths[] = $this->path . 'test_conditional_b.php';
        easytest\discover_tests($this->logger, $paths);

        $this->assert_log(array(
            array(easytest\EVENT_PASS, 'condition\\TestA::test', null),
            array(easytest\EVENT_PASS, 'condition\\TestB::test', null),
        ));
    }
}
