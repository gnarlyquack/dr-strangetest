<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace strangetest;


// Check if $var is a reference to another value in $seen.
//
// If $var is normally pass-by-value, then it can only be an explicit
// reference. If it's normally pass-by-reference, then it can either be an
// object reference or an explicit reference. Explicit references are
// marked with the reference operator, i.e., '&'.
//
// Since PHP has no built-in way to determine if a variable is a reference,
// references are identified using jank wherein $var is changed and $seen
// is checked for an equivalent change.
/**
 * @param mixed $var
 * @param string $name
 * @param array{'byval': mixed[], 'byref': mixed[]} $seen
 * @param array{'byref': null, 'byval': \stdClass} $sentinels
 * @return false|string
 */
function check_reference(&$var, $name, &$seen, $sentinels)
{
    if (\is_scalar($var) || \is_array($var) || null === $var)
    {
        $copy = $var;
        $var = $sentinels['byval'];
        $reference = \array_search($var, $seen['byval'], true);
        if (false === $reference)
        {
            $seen['byval'][$name] = &$var;
        }
        else
        {
            $reference = "&$reference";
        }
        $var = $copy;
    }
    else
    {
        $reference = \array_search($var, $seen['byref'], true);
        if (false === $reference)
        {
            $seen['byref'][$name] = &$var;
        }
        else
        {
            \assert(\is_string($reference));
            \assert(\strlen($reference) > 0);
            $copy = $var;
            $var = $sentinels['byref'];
            if ($var === $seen['byref'][$reference])
            {
                $reference = "&$reference";
            }
            $var = $copy;
        }
    }
    return $reference;
}



/**
 * @param mixed[] $array
 * @param mixed[] $seen
 * @return void
 */
function ksort_recursive(&$array, &$seen = array())
{
    if (!\is_array($array))
    {
        return;
    }

    /* Prevent infinite recursion for arrays with recursive references. */
    $temp = $array;
    $array = null;
    $sorted = \in_array($array, $seen, true);
    $array = $temp;
    unset($temp);

    if (false !== $sorted)
    {
        return;
    }
    $seen[] = &$array;
    \ksort($array);
    foreach ($array as &$value)
    {
        namespace\ksort_recursive($value, $seen);
    }
}
