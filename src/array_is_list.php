<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

// @bc 8.0 Provide implementation of array_is_list

/**
 * @param mixed[] $array
 * @return bool
 */
function array_is_list(array $array)
{
    $i = -1;
    foreach ($array as $k => $v)
    {
        ++$i;
        if ($k !== $i)
        {
            return false;
        }
    }
    return true;
}
