<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

// @bc 7.0 Provide implementation of is_iterable

/**
 * @param mixed $var
 * @return bool
 */
function is_iterable($var)
{
    return \is_array($var) || ($var instanceof \Traversable);
}
