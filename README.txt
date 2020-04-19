EasyTest
========

Inspired by various Python testing frameworks, EasyTest is a PHP testing
framework that strives to make testing as quick and painless as possible.
Features include:

* Minimal boilerplate: tests are plain old PHP objects; there is no need to
  subclass from a parent TestCase class.

* Automatic test discovery: EasyTest automatically finds and runs your tests
  if some simple naming conventions are followed; there is no need to
  manually setup and configure test suite harnesses.

* Full support of PHP's built-in assert() function. Failed assertions are
  parsed to provide a detailed failure report.

* Fixture support at the test, class, and directory level.

* Custom test loaders allow dependencies to be easily managed and injected
  into test cases.


Requirements
============

EasyTest requires PHP 5.4 or later.


Installation
============

The recommended way to install EasyTest is to use Composer. Add the following
configuration to your project's 'composer.json':

    {
        "require-dev": {
            "easytest/easytest": "*"
        }
    }

Composer will install the EasyTest executable in 'vendor/bin/'.

To make EasyTest available for all your projects, use the following command:

  composer global require 'easytest/easytest=*'

Ensure that '~/.composer/vendor/bin/' is in your PATH.


Testing
=======

To run EasyTest's self-tests, execute the following command from EasyTest's
root source directory:

  bin/easytest


Usage
=====

EasyTest is meant to be run on the command line:

  easytest [<path> ...]

EasyTest accepts the path to one or more files or directories that will be
used for discovering tests. If no path is provided, EasyTest uses the current
directory by default.

If a directory is specified, EasyTest searches it for PHP source files whose
name begins with 'test' and whose file extension is '.php' and parses them
for test cases as described in the next paragraph. EasyTest also looks for
subdirectories whose name begins with 'test' and will recursively continue
its search into any such directories.

If a file is specified, EasyTest parses the file for test cases, which is any
class whose name begins with 'test'. If a test case is found, an instance of
it is instantiated and any of its public methods whose name starts with 'test'
are run. Only one instance of each test case is created. A file may contain
multiple test cases. Namespaces are supported.

All method, class, file, and directory names are matched case-insensitively.
Names that don't match are ignored, unless the name indicates a fixture
(described below).

Any errors or test failures that occur during the test run are reported upon
completion of the run.


Writing Tests
=============

EasyTest relies upon assertions to determine whether a test passes or fails.
A test "passes" unless an assertion fails or some other error occurs. A test
ends immediately upon an assertion failure or an error, so it is generally
good practice to make tests as granular as possible.

Assertions can be made with  PHP's built-in assert() function. When an
assertion fails, the assertion state is parsed and inspected to provide an
informative failure report. For this to work, the assertion must be written
as a single-quoted string:

  assert('$expected == $actual');

There are a few inherent limitations with this:

* Non-variable values (such as function return values) cannot be inspected.

* The pseudo-variable '$this' cannot be inspected.

* When dereferencing an object property or array element, the entire object
  or array state is inspected. This will result in extraneous noise if
  reference to only one object property or array item is needed.

In such cases, values should be assigned to intermediate variables and then
used in the assertion to provide the most informative results.

Several functions are provided to mitigate these shortcomings:

easytest\assert_equal( mixed $expected, mixed $actual [, string $msg] )
    Pass if $expected == $actual.

easytest\assert_identical( mixed $expected, mixed $actual [, string $msg] )
    Pass if $expected === $actual.


Testing Exceptions
------------------

The following function can be used to check if an exception is thrown:

easytest\assert_throws( string $exception, callable $func [, string $msg] )
    * Pass if invoking $func() throws an exception that is an instance of
      $exception. The exception is returned to allow for additional testing.
    * Fail if invoking $func() does not throw an exception.
    * Re-throw the exception if invoking $func() throws an exception that is
      not an instance of $exception.

All PHP errors are converted into exceptions of type easytest\Error, which
is a subclass of PHP's built-in ErrorException class.

Assertion failures are converted into exceptions of type easytest\Failure.

Skipped tests (see below) are implemented as exceptions of type easytest\Skip.


Skipping Tests
--------------

Skipping a test may be useful if the test is incapable of being run, such as
if a certain extension isn't loaded or a particular version requirement isn't
met. Tests can be skipped by calling the following function:

easytest\skip( string $msg )
    Immediately stops execution of a test without failing it. $msg is
    required to explain why the test was skipped.

Skipping a test will not generate failures or errors in the test results.
skip() may be used in test methods to skip individual tests and in setup
fixtures (see below) to skip an entire class or directory of tests.


Test Fixtures
=============

All method and file names described below are matched case-insensitively.


setup() and teardown()
----------------------

If a test case defines a public method called 'setup', EasyTest will run it
immediately before each test method. If an error occurs or skip() is called,
then the test method is skipped. Since this probably means every test method
will be skipped, it's generally more practical to call skip() in a higher-
level setup fixture (see below).

If a test case defines a public method called 'teardown', EasyTest will run
it immediately after each test method. teardown() is run only if setup() is
successful.


setup_class()/setupClass() and teardown_class()/teardownClass()
---------------------------------------------------------------

If a test case defines a public method called 'setup_class' or 'setupClass',
EasyTest will run it after instantiating the test case and prior to running
any of the test case's test methods. If an error occurs or skip() is called
in setup_class(), then the entire test case is skipped.

If a test case defines a public method called 'teardown_class' or
'teardownClass', EasyTest will run it after running all of the test case's
test methods. teardown_class() is only run if setup_class() is successful.

Should a test case define both setup_class() and setupClass() and/or
teardown_class() and teardownClass(), an error is reported and the test case
is skipped.


setup.php and teardown.php
--------------------------

If a directory under test contains a file named 'setup.php', EasyTest will
include it before searching the directory for tests. If an error occurs or
skip() is called in the file, EasyTest will skip the directory (as well as
any subdirectories).

If the directory contains a file named 'teardown.php', EasyTest will include
it after finishing all tests in the directory (and any subdirectories) and
prior to ascending out of the directory. 'teardown.php' is only loaded if
'setup.php' is successful.

Case-sensitive file systems may allow multiple 'setup.php' and/or
'teardown.php' files with different capitalization to exist in a directory.
In such a case, an error is reported and the directory is skipped.

Fixture files may be included multiple times if EasyTest is run on multiple
paths within a test suite. Consequently, fixture files should be capable of
being included more than once.

All fixture files are loaded within a common context, thus allowing resources
to be shared across multiple files. This is done by simply assigning a
property to $this. For example:

setup.php:
    <?php
    $this->database = new DatabaseConnection();

This property can then be accessed later:

test_subdirectory/setup.php:
    <?php
    $database = $this->database;

This feature may be most useful when using custom test loaders.


Custom Test Loaders
===================

Custom test loaders are necessary if you need to provide dependencies to your
test cases during instantiation. To use a custom test loader, simply return a
callable from a directory's 'setup.php' file. That callable will then be
called to instantiate all test cases in that directory and any subdirectories
(if they don't provide their own loaders). Each directory may provide it's
own loader.

The test loader is called with one argument: the name of the test case to
instantiate. The test loader should return an instance of the test case,
otherwise an error is reported and the test case is skipped.

Here's a simple example:

test_database/setup.php:
   <?php
    // common database connection for database test cases
    $database = new DatabaseConnection();

    // custom loader to instantiate all test cases in the test_database
    // directory (and subdirectories)
    return function($className) use ($database) {
        return new $className($database);
    };
