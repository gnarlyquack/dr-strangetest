# Dr. Strangetest

This is the source code for Dr. Strangetest, a testing framework for PHP.


## Documentation

If you are looking to use Dr. Strangetest in your project, please refer to [the
website](https://www.dr-strangetest.org) for installation instructions and
other documentation.


## Installation for Development

If you're interested in hacking on the source code, these steps will get you
set up with a development environment.

Although Dr. Strangetest itself runs on PHP 5.3 and newer, the code base uses
[PHPStan](https://phpstan.org/) for static analysis, so your version of PHP
must be current enough for PHPStan.

1.  Clone the source repository to your local computer:

    git clone https://github.com/gnarlyquack/dr-strangetest

2.  Use Composer to install development requirements:

        $ composer install

3.  You'll probably want to start by running Dr. Strangetest's test suite and
    ensuring it passes. [(GNU) Make](https://www.gnu.org/software/make/) is
    used to automate this process:

        $ make

    The default target runs PHPStan on the code base:

        $ composer exec -- phpstan analyze

    and then runs Dr. Strangetest's self-test suite:

        $ ./bin/strangetest
