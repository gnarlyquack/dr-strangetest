<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace strangetest;


// These functions constitute the API to generate Dr. Strangetest exceptions

/**
 * @api
 * @param string $reason
 * @return never
 * @throws Skip
 */
function skip($reason)
{
    throw new Skip($reason);
}


/**
 * @api
 * @param string $reason
 * @return never
 * @throws Failure
 */
function fail($reason)
{
    throw new Failure($reason);
}



// @bc 5.6 Extend Failure from Exception
if (\version_compare(\PHP_VERSION, '7.0', '<'))
{
    /**
     * @api
     */
    final class Failure extends \Exception
    {
    }
}
else
{
    /**
     * @api
     */
    final class Failure extends \AssertionError
    {
    }
}


/**
 * @api
 */
final class Skip extends \Exception
{
}


final class InvalidCodePath extends \Exception
{
}
