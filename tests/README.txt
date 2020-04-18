test_assertions.php and test_exceptions.php build up the foundations of the
EasyTest client API (the functions that EasyTest provides for clients to use
in their test code).

Each test case in test_assertions.php only uses API functions that have been
tested earlier in the file. If we assume all earlier tests have passed, then
(hopefully) those functions work and we can use them in later tests.

test_exceptions.php (hopefully) establishes the functionality of the
exception-throwing API. It assumes test_assertions.php passes testing and uses
the assertion API to test those functions that throw exceptions.

Assuming these two files pass testing, then the EasyTest client API can be
used in the rest of the test suite to test EasyTest's behavior.
