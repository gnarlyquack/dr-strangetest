EasyTest
========

EasyTest is a PHP testing framework that strives to make testing as quick and
painless as possible. Features include:

* Minimal boilerplate: Tests are plain old functions or objects. Assertions
  are any function that throw an exception, or just use PHP's assert().

* Automatic test discovery: EasyTest automatically finds and runs your tests
  if basic naming conventions are followed.

* Fixture management for functions, objects, files, and directories.

* Test parameterization: Inject dependencies into your tests and/or run your
  tests multiple times with varying parameters.

* Subtests: Make multiple assertions in your tests and ensure they all run
  regardless of failure.


Requirements
============

EasyTest supports PHP versions 5.3 through 7.4.

For PHP 7, 'zend.assertions' must NOT be in production mode.


Installation
============

Install EasyTest using Composer. Add the following configuration to your
project's 'composer.json':

    {
        "require-dev": {
            "easytest/easytest": "*"
        }
    }

Composer installs the EasyTest executable 'easytest' in 'vendor/bin/'.

EasyTest attempts to load Composer's autoloader upon start-up, so you normally
won't need to do this when testing your project.


Usage
=====

EasyTest is meant to be run on the command line:

  easytest [OPTION]... [PATH]...

EasyTest accepts one or more paths to files or directories that will be used
for discovering tests. If no path is provided, EasyTest uses the current
directory by default.

If a directory is specified, EasyTest searches it for PHP source files whose
name begins with 'test' and whose file extension is '.php' and parses them for
tests as described in the next paragraph. EasyTest also looks for
subdirectories whose name begins with 'test' and will recursively continue its
search into any such directories.

If a file is specified, EasyTest parses the file for tests. A test is either a
function or class whose name begins with 'test'. If a function is found, it is
run. If a class is found, an instance of it is instantiated and any of its
public methods whose name begins with 'test' are run. A file may contain a
combination of test functions and/or classes. Namespaces are supported.

All function, class, file, and directory names are matched without regards to
case. Names that don't match are ignored unless the name indicates a fixture
(described later).

Any errors or test failures that occur during the test run are reported upon
completion of the run.

Supported options:

--verbose
    EasyTest normally indicates that skipped tests or output occurred during a
    test run but omits full details unless they happened during an error or
    failure. Running EasyTest in 'verbose' mode includes full details about
    these events in the final test report.


Writing Tests
=============

A test "passes" unless an assertion fails or some other error occurs. Failures
and errors are signalled by throwing an exception. This means a test typically
ends immediately when a failure or an error happens, although this can be
prevented by using subtests (described later).

Although PHP's assert() function can be used to make assertions, EasyTest
provides a number of assertion functions that may be more convenient.


EasyTest Assertions
-------------------

The following assertions are provided. Unless otherwise noted, each assertion
takes an optional $msg parameter that is displayed if the assertion fails.


easytest\assert_different(mixed $expected, mixed $actual, [string $msg])
    Pass if $expected !== $actual.


easytest\assert_equal(mixed $expected, mixed $actual, [string $msg])
    Pass if $expected == $actual.


easytest\assert_false(mixed $actual, [string $msg])
    Pass if $expected === false.


easytest\assert_falsy(mixed $actual, [string $msg])
    Pass if $expected == false.


easytest\assert_greater(mixed $actual, numeric $min, [string $msg])
    Pass if $actual > $min.


easytest\assert_greater_or_equal(mixed $actual, numeric $min, [string $msg])
    Pass if $actual >= $min.


easytest\assert_identical(mixed $expected, mixed $actual, [string $msg])
    Pass if $expected === $actual.


easytest\assert_less(mixed $actual, numeric $max, [string $msg])
    Pass if $actual < $max.


easytest\assert_less_or_equal(mixed $actual, numeric $max, [string $msg])
    Pass if $actual <= $max.


easytest\assert_throws(string $exception, callable $func, [string $msg])
    * Pass if invoking $func() throws an exception that is an instance of
      $exception. The exception instance is returned.
    * Fail if invoking $func() does not throw an exception.
    * Throw an exception signalling an error if invoking $func() throws an
      exception that is not an instance of $exception.

    All non-fatal PHP errors are converted into exceptions of type
    easytest\Error, which subclasses PHP's ErrorException. Note that PHP 7
    replaces many errors by throwing Error exceptions.

    Failed assertions are signalled by throwing an exception of type
    easytest\Failure. In PHP 7, this is a subclass of PHP's AssertionError.

    Skipped tests (described later) are implemented by throwing an exception
    of type easytest\Skip.


easytest\assert_true(mixed $actual, [string $msg])
    Pass if $actual === true.


easytest\assert_truthy(mixed $actual, [string $msg])
    Pass if $actual == true.


easytest\assert_unequal(mixed $expected, mixed $actual, [string $msg])
    Pass if $expected != $actual.


easytest\fail(string $reason)
    Unconditionally fail. $reason is required.


PHP assert()
------------

PHP's assert() has changed a fair bit over the life of PHP, making it tricky
to use in a consistent and backward-compatible manner across PHP versions.

The recommended usage of assert() in PHP >= 5.4.8 is:

    assert(<expression>, <description>);

<expression> is the expression to be tested by assert(). The assertion passes
if the expression results in a truthy value, otherwise it fails.

<description> is used as the failure message if the assertion fails and should
be a string explaining why the assertion failed.

The assertion expression is typically not included in the failure message if
the assertion fails. This can be remedied by making the expression a string,
although PHP 7.2 deprecates this behavior.

In PHP 7, if the 'assert.exception' configuration option is enabled and the
description is an instance of an exception, the exception is thrown if the
assertion fails. EasyTest supports this feature but only recognizes instances
of AssertionError as a test failure, so unless the exception used for the
description is a subclass of AssertionError, the test result will be reported
as an error instead of a failure. If 'assert.exception' is disabled, the
exception is instead converted to a string and used as the failure message.

PHP 5.4.8 added assert()'s description parameter, so the recommended usage of
assert() for earlier versions of PHP is:

    assert('<expression>');

Making the assertion expression a string allows it to be used as the failure
message if the assertion fails, otherwise the failure message will just be a
default, generic string.

EasyTest configures PHP's assertion options upon start-up and should not be
modified. In PHP 7, EasyTest only runs if 'zend.assertions' is NOT in
production mode (i.e., not '-1').

In PHP 5 and in PHP 7 if 'assert.exception' is disabled, failed calls to
assert() are signalled by throwing an exception of type easytest\Failure. In
PHP 7, this is a subclass of AssertionError. If 'assert.exception' is enabled
in PHP 7, then failed calls to assert() throw an AssertionError (unless an
exception instance is provided as the description, as described above).
EasyTest enables 'assert.exception' by default in PHP >= 7.2.


Writing Assertions
------------------

It's likely you will find yourself wanting to frequently make an assertion not
provided by EasyTest and, for whatever reason, using PHP's assert() directly
is not ideal. You will then want to write your own assertion, which is just a
function that, upon failure, throws an instance of easytest\Failure or, in PHP
7, an instance of AssertionError.

If your needs are simple, perhaps the easiest way to do this is to just call
PHP's assert() in your assertion function as described in the previous
section. However, this may not be ideal if the description is expensive to
create -- assert() requires you to provide a description whether the assertion
passes or fails -- or you're using an older version of PHP where assert()
doesn't take a description parameter.

More flexibly, you can check the assertion yourself, which could involve
arbitrarily complex logic, and then create a failure message only if the
assertion fails before throwing an exception. EasyTest provides some functions
to help with this. You do not need to use them, but they are used by EasyTest
itself and are offered as part of the framework.


easytest\diff(string $from, string $to, string $from_id, string $to_id)
    Return a string displaying the difference between $from and $to. $from_id
    and $to_id are used to identify the different strings in the diff output.


easytest\format_failure_message(string $assertion, [string $reason], [string
$detail])
    Compose a string using the provided string arguments.

    $assertion, if not empty, is used on the first line.

    $reason, if provided and not empty, is used on the next line.

    If both $assertion and $reason are empty, the default message "Assertion
    failed" is used.

    $detail, if provided and not empty, is used on either the third or fourth
    line, i.e., it's double-spaced after $assertion and/or $reason.


easytest\format_variable(mixed &$variable)
    Return a human-readable string representation of $variable. Scalar values
    are formatted using PHP's var_export() function, but composite data types
    (arrays and objects) are formatted a bit more concisely.


As a comprehensive example, here is an implementation of assert_identical:

    function assert_identical($expected, $actual, $msg = null) {
        if ($expected === $actual) {
            return;
        }

        $assertion = 'Assertion "$expected === $actual" failed';

        $expected = easytest\format_variable($expected);
        $actual = easytest\format_variable($actual);
        $detail = easytest\diff($expected, $actual, '$expected', '$actual');

        $reason = easytest\format_failure_message($assertion, $msg, $detail);
        easytest\fail($reason);
    }

Now let's consider the following call to the above function:

    assert_identical('one', 'two', 'I failed? :-(');

Since 'one' !== 'two', the conditional check and thus the assertion as a whole
fails. We'd like to provide some indication of the assertion that failed, so
we represent the assertion expression as a string:

    $assertion = 'Assertion "$expected === $actual" failed';

Then we replace $expected and $actual with human-readable string
representations of their values using easytest\format_variable(). $expected
and $actual are now:

    $expected = "'one'";
    $actual   = "'two'";

We use $expected and $actual to generate a diff showing why they aren't
identical. In this case it's rather obvious, and it might be tempting to just
inline the values in the assertion expression string. However, if multiline
strings or composite data types are involved, showing a diff makes it much
easier to determine why the two values differ. The resulting diff is:

    $detail = <<<'DIFF'
    - $expected
    + $actual

    - 'one'
    + 'two'
    DIFF;

easytest\diff() uses $from_id and $to_id as the identifiers in the first two
lines of the diff.

Now we stitch together a final message using $assertion, $msg, and $detail.
easytest\format_failure_message() tries remove the drudgery in this, since
$msg may be blank and/or our assertion may be simple enough that we don't want
to generate a $detail. The resulting $reason looks like:

    $reason = <<<'REASON'
    Assertion "$expected === $actual" failed
    I failed? :-(

    - $expected
    + $actual

    - 'one'
    + 'two'
    REASON;

If any argument to easytest\format_failure_message() is omitted, it's omitted
in the final message.

Finally we use this message with easytest\fail() to throw a failure exception.
The thrown exception is compatible with EasyTest regardless of PHP's version:

    easytest\fail($reason);


If you feel that there's an assertion that should really be a part of
EasyTest, please submit a pull request!


Output Buffering
----------------

EasyTest runs all tests in an output buffer. Any output that occurs during a
test run is captured and reported. EasyTest displays the output if it occurs
during an error or failure, otherwise it indicates only that output happened.
To view all output, run EasyTest in 'verbose' mode (described earlier).

If you wish to test a function that produces output, use your own output
buffers as you might normally. For example (this example uses fixture
functions which are fully described later):

    function setup_function() {
        ob_start();
    }

    function teardown_function() {
        ob_end_clean();
    }


    function test_output() {
        function_that_emits_output();
        easytest\assert_identical('Expected output', ob_get_contents());
    }

EasyTest reports an error if you start an output buffer but don't delete it.
EasyTest also reports an error if its own output buffer is deleted.


Skipping Tests
--------------

Skipping a test may be useful if the test is incapable of being run, such as
if a certain extension isn't loaded or a particular version requirement isn't
met. Tests can be skipped by calling the following function:

easytest\skip(string $reason)
    Immediately stop execution of a test without failing it. $reason is
    required to explain why the test is being skipped.

skip() may be used in test functions to skip individual tests and in fixture
setup functions (described later) to skip an entire object, file, or directory
of tests. Calling skip() outside of a test or setup function is an error.

Skipping a test does not generate a failure or error in the test results.
EasyTest normally does not report full details about skipped tests, it
indicates only that tests were skipped. To view full details about skipped
test, run EasyTest in 'verbose' mode (described earlier).


Subtests
--------

Every test function and method receives an easytest\Context object as its last
parameter. This object provides the following method:

easytest\Context::subtest(callable $test)
    Report a test failure if invoking $test() fails. A two-element array is
    returned: the first element is true or false indicating if $test passed,
    and the second element is the return value of $test (null by default) or
    null if $test failed.

The easytest\Context object also provides methods corresponding to each
EasyTest assertion. These assertion methods are identical to the assertion
functions described earlier but behave as if they were run inside
easytest\Context::subtest().

easytest\Context's subtest methods only guard against test failures. Other
exceptions (including skips) are not caught.


As a basic example of why subtests might be useful, consider a situation in
which we want to test several sets of arguments:

    function test_addition() {
        $arglists = array(
            array(0, 0, 0),
            array(2, -3, -1),
            array(-2, -3, -1),
            array(3, -3, 6),
        );

        foreach ($arglists as $arglist) {
            list($augend, $addend, $result) = $arglist;
            easytest\assert_identical(
                $result, $augend + $addend,
                "adding $augend + $addend"
            );
        }
    }

    Sample Output:
    FAILED: test_addition
    Assertion "$expected === $actual" failed
    adding -2 + -3

    - $expected
    + $actual

    - -1
    + -5

Due to the copy and paste error, the third test will fail and the fourth test
will not be run. Subtests ensure every test is run, which reveals the error in
the fourth test:

    function test_addition(easytest\Context $context) {
        $arglists = array(
            array(0, 0, 0),
            array(2, -3, -1),
            array(-2, -3, -1),
            array(3, -3, 6),
        );

        foreach ($arglists as $arglist) {
            list($augend, $addend, $result) = $arglist;
            $context->subtest(
                function() use ($augend, $addend, $result) {
                    easytest\assert_identical(
                        $result, $augend + $addend,
                        "adding $augend + $addend"
                    );
                }
            );
        }
    }

    Sample Output:
    FAILED: test_addition
    Assertion "$expected === $actual" failed
    adding -2 + -3

    - $expected
    + $actual

    - -1
    + -5



    FAILED: test_addition
    Assertion "$expected === $actual" failed
    adding 3 + -3

    - $expected
    + $actual

    - 6
    + 0

Using easytest\Context's assertion methods makes this more straightforward:

    function test_addition(easytest\Context $context) {
        $arglists = array(
            array(0, 0, 0),
            array(2, -3, -1),
            array(-2, -3, -1),
            array(3, -3, 6),
        );

        foreach ($arglists as $arglist) {
            list($augend, $addend, $result) = $arglist;
            $context->assert_identical(
                $result, $augend + $addend,
                "adding $augend + $addend"
            );
        }
    }

    Sample Output:
    FAILED: test_addition
    Assertion "$expected === $actual" failed
    adding -2 + -3

    - $expected
    + $actual

    - -1
    + -5



    FAILED: test_addition
    Assertion "$expected === $actual" failed
    adding 3 + -3

    - $expected
    + $actual

    - 6
    + 0


Test Fixtures
=============

When writing tests, you may find yourself repeatedly setting up the system
under test and its dependencies to some initial state. This initial state is
known as the test's fixture. This setup work is usually repetitive drudgery,
so it's often desirable to centralize this work in a fixture setup function.
Tests that use the same fixture can then be grouped together and obtain their
fixture from a common setup function.

You may also need to teardown your test fixture after testing. Since PHP is
garbage-collected, this is usually unnecessary, but if you're working with
external resources -- opening a file handle, setting a global configuration
variable, etc. -- you will need to do some clean up.

EasyTest provides the following functions and methods to manage your tests'
fixtures. All function and file names are matched without regards to case.


Test-Specific Teardown
----------------------

Every test function and method receives an easytest\Context object as its last
parameter. This object provides the following method:

easytest\Context::teardown(callable $teardown);
    Invoke $teardown() after the test finishes execution.

easytest\Context::teardown() may be called multiple times to register multiple
teardown callables. After the test completes, the teardown callables are run
in the order they were registered and prior to any other teardown function.

This method may be useful if a test uses a resource not needed by any other
test and needs to ensure the resource is cleaned up after the test ends. For
example, the output buffering example from earlier could be rewritten as:

    function test_output(easytest\Context $context) {
        ob_start();
        $context->teardown(function () { ob_end_clean(); });
        function_that_emits_output();
        easytest\assert_identical('Expected output', ob_get_contents());
    }


Function Setup and Teardown
---------------------------

If a test file defines a function whose name begins with 'setup_function' or
'SetupFunction', that function is run immediately before each test function.
If an error occurs or skip() is called, then the test function that would have
been run next is skipped. Since this probably means every test function will
be skipped, it's perhaps more practical to call skip() in a higher-level setup
function (see below).

If a test file defines a function whose name begins with 'teardown_function'
or 'TeardownFunction', that function is run immediately after each test
function. Function teardown is run only if function setup is successful.

Should a test file define multiple function setup and/or teardown functions,
an error is reported and the file is skipped.


Method Setup and Teardown
-------------------------

If a test class defines a public method named 'setup', that method is run
immediately before each test method. If an error occurs or skip() is called,
then the test method that would have been run next is skipped. Since this
probably means every test method will be skipped, it's perhaps more practical
to call skip() in a higher-level setup function (see below).

If a test class defines a public method named 'teardown', that method is run
immediately after each test method. Method teardown is run only if method
setup is successful.


Object Setup and Teardown
-------------------------

If a test class defines a public method named 'setup_object' or 'SetupObject',
that method is run after instantiating an object instance and prior to running
any of the object's test methods. If an error occurs or skip() is called, then
the object is skipped.

If a test class defines a public method named 'teardown_object' or
'TeardownObject', that method is run after running all of the object's test
methods and prior to deleting the object reference. Object teardown is only
run if object setup is successful.

Should a test class define multiple object setup and/or teardown methods, an
error is reported and the class is skipped.


File Setup and Teardown
-----------------------

If a file defines a function whose name begins with 'setup_file' or
'SetupFile', that function is run prior to running any other function or
instantiating any other class in the file. If an error occurs or skip() is
called, then the file is skipped.

If a file defines a function whose name begins with 'teardown_file' or
'TeardownFile', that function is run after finishing all tests in the file.
File teardown is only run if file setup is successful.

Should a test file define multiple file setup and/or teardown functions, an
error is reported and the file is skipped.


Directory Setup and Teardown
----------------------------

If a test directory contains a file named 'setup.php', EasyTest includes it
and parses it before searching the directory for tests.

If 'setup.php' defines a function whose name begins with 'setup_directory' or
'SetupDirectory', that function is run prior to continuing any more discovery
in the directory. If an error occurs or skip() is called, the directory is
skipped (as are any subdirectories).

If 'setup.php' defines a function whose name begins with 'teardown_directory'
or 'TeardownDirectory', that function is run after completing all tests in the
directory (as well as any subdirectories) and prior to ascending out of the
directory. Directory teardown is only run if directory setup is successful.

Case-sensitive file systems may allow multiple 'setup.php' files with
different capitalization to exist in a directory. In such a case, an error is
reported and the directory is skipped.

Should a 'setup.php' file define multiple directory setup and/or teardown
functions, an error is reported and the directory is skipped.


Test Parameterization
=====================

As might be evident from the list above, the provided fixture functions form a
hierarchy that lets you to manage your test fixture at different levels of
granularity. At the highest level are the directory fixture functions, which
let you to initialize state shared by an entire directory (and subdirectories)
of tests. At the lowest level are the function and method fixture functions,
which let you to initialize state for each test function or method.

Fixture functions pass their state to lower levels by returning an iterable of
arguments. Lower-level fixture functions accept these arguments by adding
parameters to their signature. As EasyTest performs its discovery, it unpacks
the arguments from the previous fixture function and provides them to the
parameters of the current fixture function. That fixture function may then add
to, remove from, alter or simply pass through the arguments it receives
(although in the latter case, the fixture function is presumably doing some
other setup action). If a fixture function is not defined for a particular
level, then arguments are directly passed to either the next lower-level
fixture function or to a test function or test class.

Let's consider an example where we wish to share a common database connection
for a directory of tests. We can define the following setup function:

setup.php:
    <?php

    function setup_directory() {
        $database = new Database();
        $database->createDatabase();
        return array($database);
    }

Then in one of our test files, we might have the following:

test.php:
    <?php

    class TestDatabase {
        private $database;

        function __construct(Database $database) {
            $this->database = $database;
        }

        function setup() {
            $this->database->reset();
        }

        function test_insert_record() {
            $this->database->insertRecord(array(1, 2));
            easytest\assert_identical(
                array(array(1, 2)),
                $this->database->records()
            );
        }

        function test_delete_record() {
            $id = $this->database->insertRecord(array(1, 2));
            easytest\assert_identical(
                array(array(1, 2)),
                $this->database->records()
            );

            $this->database->deleteRecord($id);
            easytest\assert_identical(array(), $this->database->records());
        }
    }

Once the test fixture (in this case, Database) is passed to a constructor, the
fixture can be accessed directly from the test object's shared state. However,
we could also write the above example as:

test.php:
    <?php

    function setup_function(Database $database) {
        $database->reset();
        return array($database);
    }


    function test_insert_record(Database $database) {
        $database->insertRecord(array(1, 2));
        easytest\assert_identical(
            array(array(1, 2)),
            $database->records()
        );
    }

    function test_delete_record(Database $database) {
        $id = $database->insertRecord(array(1, 2));
        easytest\assert_identical(
            array(array(1, 2)),
            $database->records()
        );

        $database->deleteRecord($id);
        easytest\assert_identical(array(), $database->records());
    }

In these two examples, since there is no file setup function, the arguments
from setup_directory() are provided directly to TestDatabase and
setup_function(). If it turns out more setup is needed, a file setup function
can be added and it will automatically receive the arguments:

test.php:
    <?php

    function setup_file(Database $database) {
        $database->loadTestData();
        return array($database);
    }

    function teardown_file(Database $database) {
        $database->clearTestData();
    }


    class TestDatabase {
        private $database;

        function __construct(Database $database) {
            $this->database = $database;
        }

        function setup() {
            $this->database->reset();
        }

        function test_insert_record() {
            $this->database->insertRecord(array(1, 2));
            easytest\assert_identical(
                array(array(1, 2)),
                $this->database->records()
            );
        }

        function test_delete_record() {
            $id = $this->database->insertRecord(array(1, 2));
            easytest\assert_identical(
                array(array(1, 2)),
                $this->database->records()
            );

            $this->database->deleteRecord($id);
            easytest\assert_identical(array(), $this->database->records());
        }
    }

Note that a teardown function has been added to clear out the test data from
the database. Fixture teardown functions don't return arguments, they only
need to cleanup the arguments provided to them.

Again, the above example could be written as:

test.php:
    <?php

    function setup_file(Database $database) {
        $database->loadTestData();
        return array($database);
    }

    function teardown_file(Database $database) {
        $database->clearTestData();
    }


    function setup_function(Database $database) {
        $database->reset();
        return array($database);
    }


    function test_insert_record(Database $database) {
        $database->insertRecord(array(1, 2));
        easytest\assert_identical(
            array(array(1, 2)),
            $database->records()
        );
    }

    function test_delete_record(Database $database) {
        $id = $database->insertRecord(array(1, 2));
        easytest\assert_identical(
            array(array(1, 2)),
            $database->records()
        );

        $database->deleteRecord($id);
        easytest\assert_identical(array(), $database->records());
    }

Note that a setup function's return value replaces whatever arguments may have
existed before it. If you define a fixture setup function, it MUST accept and
pass on the arguments given to it if those arguments are needed at lower
levels. Expanding on our database example:

test_orders.php:
    <?php

    function setup_file() {
        $processor = new PaymentProcessor();
        $processor->setTestMode();
        return array($processor);
    }


    function setup_function(Database $database, PaymentProcessor $processor) {
        return array(new OrderManager($database, $processor));
    }


    function test(OrderManager $order) {
        $order->placeOrder();
        easytest\assert_true($order->wasPlaced());
    }

This example results in an error, because setup_file() removes the Database
argument passed in by setup_directory() which setup_function() was expecting.
A fixed example:

test_orders.php:
    <?php

    function setup_file(Database $database) {
        $database->loadTestData();

        $processor = new PaymentProcessor();
        $processor->setTestMode();

        return array($database, $processor);
    }

    function teardown_file(Database $database, PaymentProcessor $processer) {
        $database->clearTestData();
    }


    function setup_function(Database $database, PaymentProcessor $processor) {
        $database->reset();
        return array(new OrderManager($database, $processor));
    }


    function test(OrderManager $order) {
        $order->placeOrder();
        easytest\assert_true($order->wasPlaced());
    }

In all of the above examples, if any test function or method desires access to
the easytest\Context object, the object is always passed as the last argument.


Directory setup functions and file setup functions offer one additional
feature: they may return an iterable of argument iterables. EasyTest then runs
subordinate tests once with each argument iterable. In order for EasyTest to
know you're returning an iterable of iterables instead of just a single
iterable, arguments must be returned using the function easytest\arglists(),
which takes an iterable of iterables as its only parameter.

setup.php:
    <?php

    function setup_directory() {
        $db1 = new DatabaseX();
        $db1->createDatabase();

        $db2 = new DatabaseY();
        $db2->createDatabase();

        return easytest\arglists(array(
            'database x' => array($db1),
            'database y' => array($db2),
        ));
    }

test_orders.php:
    <?php

    function setup_file(Database $database) {
        $database->loadTestData();

        $processor1 = new PaymentProcessorA();
        $processor1->setTestMode();

        $processor2 = new PaymentProcessorB();
        $processor2->setTestMode();

        return easytest\arglists(array(
            'processor a' => array($database, $processor1),
            'processor b' => array($database, $processor2),
        ));
    }

    function teardown_file($arglists) {
        // since both processors use the same database, we only need to clear
        // out the database once
        $database = $arglists['processor a'][0];
        $database->clearTestData();
    }


    function setup_function(Database $database, PaymentProcessor $processor) {
        $database->reset();
        return array(new OrderManager($database, $processor));
    }


    function test(OrderManager $order) {
        $order->placeOrder();
        easytest\assert_true($order->wasPlaced());
    }

test() is now run four times using every combination of database and payment
processor. If an error or failure occurs, the failed run is identified using
the keys of the array passed to easytest\arglists(). In this example, a
failure of test() might be identified as '(database x, processor b)'.

Note one important change in this example: arguments are no longer unpacked
for file and directory teardown functions. Since these functions are only
called once per file or directory, the entire collection of arguments is
passed to them. Whatever the setup function passed to easytest\arglists() is
passed to the teardown function.
