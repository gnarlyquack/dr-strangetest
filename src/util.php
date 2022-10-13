<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace strangetest;


/**
 * @param string $identifier A valid identifier
 * @return string The identifier with ascii letters normalized to lowercase
 */
function normalize_identifier($identifier)
{
    $result = '';
    for ($i = 0, $c = \strlen($identifier); $i < $c; ++$i)
    {
        $chr = $identifier[$i];
        $ord = \ord($chr);
        if (($ord >= 65) && ($ord <= 90))
        {
            $ord += 32; // Make uppercase ascii characters lowercase
        }
        $result .= \chr($ord);
    }

    return $result;
}


const _RESOLVE_INVALID  = 0;
const _RESOLVE_DEFAULT  = 1;
const _RESOLVE_GLOBAL   = 2;
const _RESOLVE_FUNCTION = 3;

/**
 * @param string $name
 * @param string $default_class
 * @return ?string
 */
function resolve_test_name($name, NamespaceInfo $default_namespace, $default_class = '')
{
    // Ensure that the namespace ends in a trailing namespace separator
    \assert(
        (0 === \strlen($default_namespace->name))
        || ('\\' === \substr($default_namespace->name, -1)));
    // Ensure that the namespace is included in the classname (because we
    // probably want want to change this)
    \assert(
        (0 === \strlen($default_class))
        || ((0 === \strlen($default_namespace->name)) && (false === \strpos($default_class, '\\')))
        || ((\strlen($default_namespace->name) > 0) && (0 === \strpos($default_class, $default_namespace->name))));

    $namespace = '';
    $class = '';
    $function = '';
    $status = namespace\_RESOLVE_DEFAULT;
    $start = 0;
    for ($i = 0, $c = \strlen($name); $status && ($i < $c); ++$i)
    {
        $char = $name[$i];

        if ('\\' === $char)
        {
            if ($i === 0)
            {
                $status = namespace\_RESOLVE_GLOBAL;
                ++$start;
            }
            else
            {
                if ($i > $start)
                {
                    $namespace_part = \substr($name, $start, $i - $start);
                    if ($namespace)
                    {
                        $namespace .= $namespace_part;
                    }
                    elseif ($status !== namespace\_RESOLVE_GLOBAL)
                    {
                        if (isset($default_namespace->use[$namespace_part]))
                        {
                            $namespace = $default_namespace->use[$namespace_part];
                        }
                        else
                        {
                            // @todo always prepend the current namespace
                            $namespace = $namespace_part;
                        }
                    }

                    $namespace .= '\\';
                    $start = $i + 1;
                }
                else
                {
                    $status = namespace\_RESOLVE_INVALID;
                }
            }
        }
        elseif (':' === $char)
        {
            if ((($i + 1) < $c) && (':' === $name[$i + 1]))
            {
                if ($i === 0)
                {
                    if ($default_class)
                    {
                        $status = namespace\_RESOLVE_FUNCTION;
                    }
                    else
                    {
                        throw new \Exception(
                            \sprintf('Tried to force-resolve \'%s\' as a function, but this can only be done from within a method', $name));
                    }
                }
                elseif ($i > $start)
                {
                    $class = \substr($name, $start, $i - $start);
                    if (($status === namespace\_RESOLVE_DEFAULT) && !$namespace)
                    {
                        if (isset($default_namespace->use[$class]))
                        {
                            $class = $default_namespace->use[$class];
                        }
                        else
                        {
                            $namespace = $default_namespace->name;
                        }
                    }
                    $class .= '::';
                }
                else
                {
                    $status = namespace\_RESOLVE_INVALID;
                }

                ++$i;
                $start = $i + 1;
            }
            else
            {
                $status = namespace\_RESOLVE_INVALID;
            }
        }
        else
        {
            $char = namespace\_validate_identifier_char($char, $i - $start);
            if ($char === null)
            {
                $status = namespace\_RESOLVE_INVALID;
            }
            else
            {
                $name[$i] = $char;
            }
        }
    }

    if ($i > $start)
    {
        $function = \substr($name, $start, $i);

        if (!$class && !$namespace && ($status !== namespace\_RESOLVE_GLOBAL))
        {
            if ($default_class && ($status === namespace\_RESOLVE_DEFAULT))
            {
                // @todo Pass is normalized class name to resolve_test_name()
                $class = namespace\normalize_identifier($default_class) . '::';
            }
            elseif (isset($default_namespace->use_function[$function]))
            {
                $function = $default_namespace->use_function[$function];
            }
            else
            {
                $namespace = $default_namespace->name;
            }
        }
    }
    else
    {
        $status = namespace\_RESOLVE_INVALID;
    }

    if ($status)
    {
        $result = $namespace . $class . $function;
    }
    else
    {
        $result = null;
    }

    return $result;
}


/**
 * Valid PHP identifiers are represented by the following regex:
 *      ^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$
 *
 * This maps to the following ascii characters:
 *  48 -  57: Numbers, but not for the first character
 *  65 -  90: Uppercase ascii characters
 *        95: Underscore
 *  97 - 122: Lowercase ascii characters
 * 128 - 255: "Extended" ascii characters
 *
 * Note that lowercase and uppercase ascii characters are treated
 * case-insensitively by the PHP parser
 *
 * @param string $char
 * @param int $pos
 * @return ?string
 */
function _validate_identifier_char($char, $pos)
{
    \assert($pos >= 0);

    $ord = \ord($char);
    if (($ord >= 65) && ($ord <= 90))
    {
        $char = \chr($ord + 32); // Make uppercase ascii characters lowercase
    }
    elseif (!(
        // numbers allowed other than as initial character
        (($ord >= 48) && ($ord <= 57) && $pos)
        // underscore
        || ($ord === 95)
        // lowercase ascii characters
        || (($ord >= 97) && ($ord <= 122))
        // extended ascii characters
        || ($ord >= 128)))
    {
        $char = null;
    }

    return $char;
}


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
