EasyTest is a testing framework for PHP.

In addition to this README, you may also want to refer to the wiki:
https://github.com/gnarlyquack/easytest/wiki



Requirements
============

EasyTest supports PHP versions 5.3 through 7.4.

For PHP 7, 'zend.assertions' must NOT be in production mode.



Installation
============

Phar (PHP Archive)
------------------

The recommended way to use EasyTest is to download a Phar from the following
URL:
https://github.com/gnarlyquack/easytest/releases/latest/download/easytest.phar

Place the Phar in the root directory of your project. You can then run
EasyTest as follows:

    php easytest.phar

If you make the Phar executable you can run it directly:

    ./easytest.phar

Note that PHP must have the Phar extension enabled for this to work.


Composer
--------

Install EasyTest using Composer:

    composer require --dev easytest/easytest

Composer installs the 'easytest' executable in its 'bin-dir', which defaults
to 'vendor/bin'. Assuming the default 'bin-dir', you can then run EasyTest as
follows:

    ./vendor/bin/easytest


From hereon, references to 'easytest' refer to the location of the executable.



Usage
=====

Run EasyTest from the command line:

  easytest [OPTION...] [TEST...]

EasyTest accepts zero or more TEST specifiers (described later) to search for
tests. If no test specifier is provided, EasyTest defaults to searching the
current directory for tests.

A "test" is any function or method whose name begins with 'test'. When such a
function or method is found, it is run. A test "passes" unless an assertion
fails or some other error occurs. Failures and errors are signalled by the
throwing of an exception. This means a test typically ends immediately when a
failure or an error happens, although you can use subtests (described later)
to continue testing after a failure.

Test methods are organized into test classes, which is any class whose name
begins with 'test'. When such a class is found, an instance of it is
instantiated and it is searched for test methods.

Test functions and test classes are organized into test files, which is any
PHP source file whose name begins with 'test' and whose file extension is
'.php'. When such a file is found, it is included and searched for test
functions and test classes. A test file may contain any combination of test
functions and/or test classes.

Test files may be organized into test directories, which is any directory
whose name begins with 'test'. When such a directory is found, it is searched
for test files and also for subdirectories whose name also beings with 'test'.

All names are matched case-insensitively. Names that don't match are ignored
unless the name indicates a fixture function (described later).

If your project uses Composer, EasyTest attempts to load Composer's autoloader
so your project is automatically visible to your tests.

Any errors or test failures during the test run are reported upon completion
of the run.


Specifying Tests
----------------

You can run individual tests or a subset of tests in your test suite by
invoking EasyTest with one or more test specifiers.


Path Specifiers

Use a path specifier to run individual test directories or files.

    [--path=]PATH

PATH is a relative or absolute path to a file or directory. The leading
'--path=' specifier is only necessary if the start of a path name conflicts
with one of test specifiers described in this section. If the '--path=' prefix
is used in the first test specifier, it must be preceded by '--' and a space
to distinguish the test specifier from other options.

A path specifier that corresponds to a file may be followed by a combination
of function specifiers and class specifiers (described below). The file should
contain the definitions of the functions and classes specified by the function
and class specifiers. If a specified function and/or class is not found in the
file, an error is reported.


Function Specifiers

Use a function specifier to run individual test functions within a test file.

    --function=FUNCTION[,FUNCTION...]

FUNCTION is a fully-qualified function name. Multiple functions may be
specified by separating each name with a comma. A function specifier must be
preceded by a file specifier as described above.


Class Specifiers

Use a class specifier to run individual test classes with a test file and
individual test methods within a test class.

    --class=CLASS[,CLASS...][::METHOD[,METHOD...]]

CLASS is a fully-qualified class name. Multiple classes may be specified by
separating each name with a comma. One or more methods may be specified for
the last class in the list by separating the list of methods from the class
name with '::'. Multiple methods may be specified by separating each name with
a comma. A class specifier must be preceded by a file specifier as described
above.



Command Line Options
--------------------

The follow options are supported:

--verbose
    Details about skipped tests and output during a test run are usually
    omitted unless they occur during an error or a failure. Enabling 'verbose'
    mode includes these details regardless of errors or failures.



Making Assertions
=================

Although PHP's assert() function can be used to make assertions, EasyTest
provides a number of assertion functions that may be more convenient.


EasyTest Assertions
-------------------

The following assertions are provided. Unless otherwise noted, each assertion
takes an optional $msg parameter that is displayed if the assertion fails.


easytest\assert_different(mixed $expected, mixed $actual, [string $msg])
    Passes if $expected !== $actual.


easytest\assert_equal(mixed $expected, mixed $actual, [string $msg])
    Passes if $expected == $actual.


easytest\assert_false(mixed $actual, [string $msg])
    Passes if $expected === false.


easytest\assert_falsy(mixed $actual, [string $msg])
    Passes if $expected == false.


easytest\assert_greater(mixed $actual, mixed $min, [string $msg])
    Passes if $actual > $min.


easytest\assert_greater_or_equal(mixed $actual, mixed $min, [string $msg])
    Passes if $actual >= $min.


easytest\assert_identical(mixed $expected, mixed $actual, [string $msg])
    Passes if $expected === $actual.


easytest\assert_less(mixed $actual, mixed $max, [string $msg])
    Passes if $actual < $max.


easytest\assert_less_or_equal(mixed $actual, mixed $max, [string $msg])
    Passes if $actual <= $max.


easytest\assert_throws(string $exception, callable $callable, [string $msg])
    - Passes if invoking $callable() throws an exception that is an instance
      of $exception. The exception instance is returned.
    - Fails if invoking $callable() does not throw an exception.
    - Throws an exception (signalling an error) if invoking $callable() throws
      an exception that is not an instance of $exception.

    All non-fatal PHP errors are converted into exceptions of type
    easytest\Error, which subclasses PHP's ErrorException, and thrown.

    Failed assertions are signalled by throwing an exception of type
    easytest\Failure. In PHP 7, this is a subclass of PHP's AssertionError.

    Skipped tests (described later) are implemented by throwing an exception
    of type easytest\Skip.


easytest\assert_true(mixed $actual, [string $msg])
    Passes if $actual === true.


easytest\assert_truthy(mixed $actual, [string $msg])
    Passes if $actual == true.


easytest\assert_unequal(mixed $expected, mixed $actual, [string $msg])
    Passes if $expected != $actual.


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
the assertion fails. This can be remedied by writing the expression as a
string, although PHP 7.2 deprecates this behavior. This means the expression
itself can never be a string: PHP < 7.2 interprets it as code and PHP >= 7.2
triggers an error.

In PHP 7, if the 'assert.exception' configuration option is enabled and the
description is an instance of an exception, the exception is thrown if the
assertion fails. EasyTest supports this feature, but unless the exception is
an instance of AssertionError, the test result is reported as an error instead
of a failure. If 'assert.exception' is disabled, the exception is instead
converted to a string and used as the failure message.

PHP 5.4.8 added assert()'s description parameter, so the recommended usage of
assert() for earlier versions of PHP is:

    assert('<expression>');

Writing the assertion expression as a string allows it to be used as the
failure message if the assertion fails, otherwise the failure message is just
a default, generic string.

EasyTest configures PHP's assertion options upon start-up and should not be
modified. In PHP 7, EasyTest only runs if 'zend.assertions' is NOT in
production mode (i.e., not '-1').

In PHP 5 and in PHP 7 if 'assert.exception' is disabled, failed calls to
assert() are signalled by throwing an exception of type easytest\Failure. In
PHP 7, this is a subclass of AssertionError. If 'assert.exception' is enabled
in PHP 7, failed calls to assert() throw an AssertionError (unless an
exception instance is provided as the description, as described above).
EasyTest enables 'assert.exception' by default in PHP >= 7.2.


Writing Custom Assertions
-------------------------

An assertion is just a function that, if some condition is not met, throws an
instance of easytest\Failure or, in PHP 7, an instance of AssertionError.

The simplest custom assertions will just wrap PHP's assert() or another
EasyTest assertion. More complex cases will want to implement the assertion
logic manually, which generally boils down to:

1.  Check if the assertion passes. This can consist of arbitrarily complex
logic that ultimately depends on the outcome of a condition. If the condition
is true, the assertion passes, otherwise it fails. If the assertion fails:

2.  Format a failure message. EasyTest offers some functions (described below)
to help with this. There is no requirement to use them, but they are used by
EasyTest itself and offered as part of the framework.

3.  Throw a failure exception. EasyTest provides easytest\fail() (described
above), which throws an exception that is compatible with Easytest regardless
of the version of PHP in use.

The following functions are provided to help with writing custom assertions:


easytest\diff(mixed &$from, mixed &$to, string $from_id, string $to_id,
[bool $strict = true])
    Returns a string displaying the difference between $from and $to. $from_id
    and $to_id are used to identify which values changed in the diff. If
    $strict is set to false, then the diff is generated using loose comparison
    (==) between $from and $to, otherwise the diff is generated using strict
    comparison (===).

    Note that $from and $to are received as references to detect recursive
    values.


easytest\format_failure_message(string $assertion, [string $reason],
[string $detail])
    Composes a string using the provided string arguments.

    $assertion, if not empty, is used on the first line.

    $reason, if provided and not empty, is used on either the second or first
    line, depending on whether or not $assertion is empty.

    If both $assertion and $reason are empty, the default message "Assertion
    failed" is used.

    $detail, if provided and not empty, is used on either the third or fourth
    line and below, i.e., it's double-spaced after $assertion and/or $reason.


easytest\format_variable(mixed &$variable)
    Returns a human-readable string representation of $variable. Scalar values
    are formatted using PHP's var_export() function, but composite data types
    (arrays and objects) are formatted a bit more concisely.

    Note that $variable is received as a reference in order to detect a
    recursive value.


Skipping Tests
==============

Tests can be skipped by calling the following function:

easytest\skip(string $reason)
    Immediately stops execution of a test without failing it. $reason is
    required to explain why the test is being skipped.

Skipping a test may be useful if the test is incapable of being run, such as
if a certain extension isn't loaded or a particular version requirement isn't
met. Typically you should make these checks at the beginning of a test and
only continue if the necessary requirements are met.

skip() may be used in test functions to skip individual tests and in fixture
setup functions (described later) to skip an entire object, file, or directory
of tests. Calling skip() outside of a test or setup function is an error.

Skipping a test does not generate a failure or an error in the test results.
Although EasyTest indicates when tests are skipped, it does not report details
unless run in 'verbose' mode (described earlier).


Context Objects
===============

Every test function and method receives an easytest\Context object as its last
parameter. This object implements the following features:


Subtests
--------

Subtests allow you to make multiple assertions in a test and continue the test
even if an assertion fails. The Context object mirrors EasyTest's assertions
but returns true or false indicating success or failure.


easytest\Context::assert_different(mixed $expected, mixed $actual,
[string $description])
    Passes if $expected !== $actual.


easytest\Context::assert_equal(mixed $expected, mixed $actual,
[string $description])
    Passes if $expected == $actual.


easytest\Context::assert_false(mixed $actual, [string $description])
    Passes if $expected === false.


easytest\Context::assert_falsy(mixed $actual, [string $description])
    Passes if $expected == false.


easytest\Context::assert_greater(mixed $actual, mixed $min,
[string $description])
    Passes if $actual > $min.


easytest\Context::assert_greater_or_equal(mixed $actual, mixed $min,
[string $description])
    Passes if $actual >= $min.


easytest\Context::assert_identical(mixed $expected, mixed $actual,
[string $description])
    Passes if $expected === $actual.


easytest\Context::assert_less(mixed $actual, mixed $max, [string $description])
    Passes if $actual < $max.


easytest\Context::assert_less_or_equal(mixed $actual, mixed $max,
[string $description])
    Passes if $actual <= $max.


easytest\Context::assert_throws(string $exception, callable $callable,
[string $description], [object &$result])
    - Passes if invoking $callable() throws an exception that is an instance
      of $exception. The exception instance is saved in $result.
    - Fails if invoking $callable() does not throw an exception.
    - Throws an exception (signalling an error) if invoking $callable() throws
      an exception that is not an instance of $exception.


easytest\Context::assert_true(mixed $actual, [string $description])
    Passes if $actual === true.


easytest\Context::assert_truthy(mixed $actual, [string $description])
    Passes if $actual == true.


easytest\Context::assert_unequal(mixed $expected, mixed $actual,
[string $description])
    Passes if $expected != $actual.


easytest\Context::fail(string $reason)
    Unconditionally fail. $reason is required.


Test-Specific Teardown
----------------------

easytest\Context::teardown(callable $teardown)
    Invokes $teardown() after the current test finishes execution.

    This method may be called multiple times to register multiple callables.
    After the test completes, the callables are run in the order they were
    registered and prior to any other teardown function.


Test Dependencies
-----------------

Test dependencies allow a test (the "dependent") to require one or more other
tests ("requisites") to first pass before the dependent is executed.
Dependencies should be declared at the beginning of a test. If a dependent is
run before any of its requisites, it is postponed until all requisites have
been run. If any requisites are not successful, the dependent is skipped.

A dependent may declare its requisites by calling the following method:

easytest\Context::depend_on(string $requisite, [string ...])
    Declare a dependency on $requisite. Multiple requisites can be specified.
    $requisite is the name of a test function or method. Methods are specified
    by writing them as 'ClassName::methodName'. A parameterized test run
    (described below) can be indicated by specifying the run name in
    parenthesis after the function or method name, e.g., 'foo(run)'.

    Although this method may be called multiple times for multiple requisites,
    it is instead recommended to provide all requisites in one call.

    If any provided name is not fully qualified, a fully qualified name is
    determined as follows:
    - If the name is a function name, it's assumed to be either a function in
      the current namespace or a method in the current class.
    - If the name is a method name with an unqualified class name, the class
      is assumed to be in the current namespace.
    - If called from a method, a function in the current namespace can be
      specified by passing '::function_name'
    - If no run is specified, the test is assumed to be a part of the current
      run. A parameterized test may depend on a non-parameterized test by
      using empty parenthesis, e.g., 'foo()'.
    Note that, in order to specify a global name from within a namespace, the
    name must start with a backslash.

    If called with one requisite, this method returns the state saved by the
    requisite or null. If called with multiple requisites, the state is
    returned in an associative array indexed by the names used in the call. If
    a requisite did not save any state it will not appear in the array.

Requisites can save state for dependents to use by calling the following
method. Requisites would typically call this at the end of the test.

easytest\Context::set(mixed $state)
    Make $state available to other tests for the rest of the test run.
    Dependents retrieve $state by calling easytest\Context::depend_on().



Test Fixtures
=============

A test's "fixture" is the initial state of the system under test as well as
its dependencies. Setting this up is usually repetitive drudgery and may often
constitute the majority of the work when writing your tests. It's therefore
desirable to centralize this work in a fixture setup function. Tests using the
same fixture can then be grouped together and obtain their fixture from this
common setup function.

You may also need to clean up, or "teardown", your test fixture after testing.
Since PHP is garbage collected this is usually unnecessary, but if you're
working with external resources -- opening a file handle, setting a global
configuration variable, etc. -- you need to do some clean up.

Most of the time you want to recreate the fixture from scratch for each test.
This is to ensure, as much as possible, the outcome of each test depends only
on its fixture and not on other tests or external phenomena, a concept often
called "test isolation". In practice, creating entirely new fixture for each
test this isn't always possible or even, at times, desirable.

Fixtures can be set up and torn down for functions, methods, objects, files,
and directories. Setup is done immediately prior to testing or doing discovery
on the associated item and teardown is done immediately after. Setup can fail
if an error occurs or easytest\skip() is called. If setup fails, testing or
discovery of the associated item and its teardown is skipped. If setup
succeeds, teardown is always attempted regardless of the outcome of testing or
discovery (barring a fatal PHP error).


Fixture Functions
-----------------

The names recognized by EasyTest to manage your fixtures are described below
(except for easytest\Context::teardown, which is described above). EasyTest
matches all names without regards to case and matches on both CamelCase and
snake_case variants of the name. If conflicting setup and/or teardown names
are ever found, an error is reported and the associated item is skipped.


Function Setup and Teardown

If a test file defines a function whose name begins with 'setup', that
function is run before every test function.

If a test file defines a function whose name begins with 'teardown', that
function is run after every test function.


Method Setup and Teardown

If a test class defines a public method named 'setup', that method is run
before every test method.

If a test class defines a public method named 'teardown', that method is run
after every test method.


Object Setup and Teardown

If a test class defines a public method named 'setup_object', that method is
run after an object instance is instantiated and before any other method.

If a test class defines a public method named 'teardown_object', that method
is run after running all of the object's tests.


File Setup and Teardown

If a test file defines a function whose name begins with 'setup_file', that
function is run before running any other function or instantiating any other
class in the file.

If test file defines a function whose name begins with 'teardown_file', that
function is run after finishing all tests in the file.

If test file defines a function whose name begins with 'teardown_run', that
function is run after each parameterized test run (described below).


Directory Setup and Teardown

If a test directory contains a file named 'setup.php', EasyTest includes it
before searching the directory for tests and including any other file.

If 'setup.php' defines a function whose name begins with 'setup', that
function is run prior to continuing any more discovery in the directory.

If 'setup.php' defines a function whose name begins with 'teardown', that
function is run after completing all tests in the directory (as well as any
subdirectories) and prior to ascending out of the directory.

If 'setup.php' defines a function whose name begins with 'teardown_run', that
function is run after each parameterized test run (described below).


Providing Fixture to Tests
--------------------------

The fixture functions described above form a natural hierarchy in which to
manage test state with directories at the top and individual tests at the
bottom. Setup functions higher in the hierarchy can pass state to setup
functions and/or tests lower in the hierarchy by returning an array of
arguments. As EasyTest performs discovery, these arguments are unpacked and
automatically passed to subordinate fixture functions, class constructors, and
test functions, which accept them by adding parameters to their signature.

Subordinate setup functions "intercept" state from higher setup functions.
Whatever these functions return replaces whatever arguments are passed to it.
This is true regardless of whether or not a function explicitly accepts the
arguments provided to it. Functions may add to, remove from, alter or simply
pass through the arguments they receive. If a setup function is not defined at
a particular level in the hierarchy, arguments are passed directly to the next
subordinate setup function, test function or test class.

Arguments are also passed to teardown functions, but these functions never
return arguments, they only clean up the arguments given to them.

Object fixture methods are an exception: since they always have access to
their object's shared state, they neither receive nor return parameters.
Objects receive state by accepting it in their constructors.


Multiple Parameterized Test Execution
-------------------------------------

Directory setup functions and file setup functions may return multiple sets of
arguments. EasyTest then runs subordinate fixture functions and tests once
with each set of arguments. In order for EasyTest to know you're returning
multiple sets of arguments instead of just one, arguments must be returned
using the function easytest\arglists(), which takes an iterable of iterables
as its only parameter. If a failure or an error occurs during one of the runs,
the keys of the iterable passed to easytest\arglists() are used in the test
report to identify which run had the problem.

If a directory or file setup function returns multiple sets of arguments,
arguments are no longer unpacked when calling the corresponding directory or
file teardown function. Since these functions are only called once per
directory or file, the entire collection of arguments is passed to them.
Whatever is passed to easytest\arglists() by a setup function is passed to its
teardown function. For this reason, the teardown_run functions are provided.
These functions are called after each run and are passed the unpacked list of
arguments used for the run.
