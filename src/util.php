<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace strangetest;


final class ReferenceChecker extends struct
{
    /** @var null */
    const BYREF_SENTINEL = null;

    /** @var mixed[] */
    private $byref_seen = array();

    /**
     * We'd really like to make this a constant, but PHP won't let us do that
     * with an object instance, so we'll just have to initialize it every time
     * we instantiate a new class. :-(
     * @var \stdClass
     */
    private $byval_sentinel;

    /** @var mixed[] */
    private $byval_seen = array();


    public function __construct()
    {
        $this->byval_sentinel = new \stdClass;
    }


    /**
     * Check if $var is a reference to a value previously passed to the
     * reference checker. Returns false if not, or the name of the previous
     * value that this value references.
     *
     * @param mixed $var
     * @param string $name
     * @return false|string
     */
    public function check_variable(&$var, $name)
    {
        // Since PHP has no built-in way to determine if a variable is a
        // reference, references are identified using jank wherein $var is
        // changed to a value that it could not possibly be (i.e., one of the
        // sentinel values, depending on the value's type) and $seen is checked
        // to see if that value is found.
        //
        // If $var is normally pass-by-value, then it can only be an explicit
        // reference. If it's normally pass-by-reference, then it can either be an
        // object reference or an explicit reference. Explicit references are
        // marked with the reference operator, i.e., '&'.
        if (\is_scalar($var) || \is_array($var) || null === $var)
        {
            $copy = $var;
            $var = $this->byval_sentinel;
            $reference = \array_search($var, $this->byval_seen, true);
            if (false === $reference)
            {
                $this->byval_seen[$name] = &$var;
            }
            elseif ($reference === $name)
            {
                $reference = false;
            }
            else
            {
                $reference = "&$reference";
            }
            $var = $copy;
        }
        else
        {
            $reference = \array_search($var, $this->byref_seen, true);
            if (false === $reference)
            {
                $this->byref_seen[$name] = &$var;
            }
            elseif ($reference === $name)
            {
                $reference = false;
            }
            else
            {
                \assert(\is_string($reference));
                \assert(\strlen($reference) > 0);
                $copy = $var;
                $var = self::BYREF_SENTINEL;
                if ($var === $this->byref_seen[$reference])
                {
                    $reference = "&$reference";
                }
                $var = $copy;
            }
        }

        return $reference;
    }
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


/**
 * @template T
 */
interface ListIterator
{
    /**
     * @return T
     */
    public function next();

    /**
     * @return bool
     */
    public function valid();
}


/**
 * @template T
 * @implements ListIterator<T>
 */
final class ListForwardIterator extends struct implements ListIterator
{
    /** @var T[] */
    private $list;

    /** @var int */
    private $index;

    /** @var int */
    private $len;


    /**
     * @param T[] $list
     */
    public function __construct(array $list)
    {
        \assert(\array_is_list($list));

        $this->list = $list;
        $this->index = 0;
        $this->len = \count($list);
    }


    public function next()
    {
        $result = $this->list[$this->index++];
        return $result;
    }


    public function valid()
    {
        return $this->index < $this->len;
    }
}


/**
 * @template T
 * @implements ListIterator<T>
 */
final class ListReverseIterator extends struct implements ListIterator
{
    /** @var T[] */
    private $list;

    /** @var int */
    private $index;

    /** @var int */
    private $len;


    /**
     * @param T[] $list
     */
    public function __construct(array $list)
    {
        \assert(\array_is_list($list));

        $this->list = $list;
        $this->len = \count($list);
        $this->index = $this->len - 1;
    }


    public function next()
    {
        $result = $this->list[$this->index--];
        return $result;
    }


    public function valid()
    {
        return $this->index >= 0;
    }
}
