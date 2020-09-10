<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace easytest;


function diff(&$from, &$to, $from_name, $to_name) {
    if ($from_name === $to_name) {
        throw new \Exception('Parameters $from_name and $to_name must be different');
    }

    $state = new _DiffState();

    namespace\_diff_values(
        namespace\_process_value($from, $from_name, $state),
        namespace\_process_value($to, $to_name, $state),
        $state
    );

    $diff = \implode("\n", \array_reverse($state->diff));
    return "- $from_name\n+ $to_name\n\n$diff";
}


final class _DiffState extends struct {
    public $diff = array();
    public $seen = array('byval' => array(), 'byref' => array());
    public $sentinels = array('byref' => null);

    public function __construct() {
        $this->sentinels['byval'] = new \stdClass();
    }
}



const _FORMAT_INDENT = '    ';


final class _DiffCopy {
    public $value;

    public function __construct($value) {
        $this->value = $value;
    }

    public function __toString() {
        return "  {$this->value}";
    }
}


final class _DiffInsert {
    public $value;

    public function __construct($value) {
        $this->value = $value;
    }

    public function __toString() {
        return "+ {$this->value}";
    }
}


final class _DiffDelete {
    public $value;

    public function __construct($value) {
        $this->value = $value;
    }

    public function __toString() {
        return "- {$this->value}";
    }
}


final class _Edit extends struct {
    public $flen;
    public $tlen;
    public $m;

    public function __construct($flen, $tlen, $m) {
        $this->flen = $flen;
        $this->tlen = $tlen;
        $this->m = $m;
    }
}


final class _DiffPosition {
    const NONE = 0;
    const START = 1;
    const MIDDLE = 2;
    const END = 3;
}


final class _ValueType {
    const ARRAY = 1;
    const OBJECT = 2;
    const REFERENCE = 3;
    const SCALAR = 4;
    const STRING = 5;
    const STRING_PART = 6;
    const RESOURCE = 7;
}


final class _Value extends struct {
    /** @var _ValueType::* */
    private $type;

    /** @var ?string */
    private $name;

    /** @var null|int|string */
    private $key;

    /** @var mixed */
    private $value;

    /** @var int */
    private $scope;

    /** @var int */
    private $cost;

    /** @var ?_Value[] */
    private $subvalues;


    /**
     * @param _ValueType::* $type
     * @param ?string $name
     * @param null|int|string $key
     * @param mixed $value
     * @param int $scope
     * @param int $cost
     * @param ?_Value[] $subvalues
     */
    public function __construct(
        $type, $name, $key, &$value, $scope = 0, $cost = 1, array $subvalues = null
    ) {
        $this->type = $type;
        $this->name = $name;
        $this->key = $key;
        $this->value = &$value;
        $this->scope = $scope;
        $this->cost = $cost;
        $this->subvalues = $subvalues;
    }


    /**
     * @return _ValueType::*
     */
    public function type() {
        return $this->type;
    }


    /**
     * @return ?string
     */
    public function name() {
        return $this->name;
    }


    /**
     * @return null|int|string
     */
    public function key() {
        return $this->key;
    }


    /**
     * @return mixed
     */
    public function &value() {
        return $this->value;
    }


    /**
     * @return int
     */
    public function cost() {
        return $this->cost;
    }


    /**
     * @return int
     */
    public function scope() {
        return $this->scope;
    }


    /**
     * @return ?_Value[]
     */
    public function subvalues() {
        return $this->subvalues;
    }
}


/**
 * @param int $scope
 */
function _process_value(&$var, $name, _DiffState $state, $key = null, $scope = 0) {
    $reference = namespace\_check_reference($var, $name, $state->seen, $state->sentinels);
    if ($reference) {
        return new _Value(_ValueType::REFERENCE, $reference, $key, $var, $scope);
    }

    if (\is_resource($var)) {
        return new _Value(_ValueType::RESOURCE, $name, $key, $var, $scope);
    }

    if (\is_string($var)) {
        $lines = \explode("\n", $var);
        $cost = \count($lines);
        $subvalues = array();
        $subvalues[] = new _Value(_ValueType::STRING_PART, null, $key, $lines[0], $scope);
        for ($i = 1; $i < $cost; ++$i) {
            $subvalues[] = new _Value(_ValueType::STRING_PART, null, null, $lines[$i]);
        }
        return new _Value(_ValueType::STRING, $name, $key, $var, $scope, $cost, $subvalues);
    }

    if (\is_array($var)) {
        $cost = 1;
        $subvalues = array();
        foreach ($var as $k => &$v) {
            $subname = \sprintf('%s[%s]', $name, \var_export($k, true));
            $subvalue = namespace\_process_value($v, $subname, $state, $k, $scope + 1);
            $cost += $subvalue->cost();
            $subvalues[] = $subvalue;
        }
        return new _Value(_ValueType::ARRAY, $name, $key, $var, $scope, $cost, $subvalues);
    }

    if (\is_object($var)) {
        $cost = 1;
        $subvalues = array();
        // #BC(5.4): use variable for array cast in order to create references
        $values = (array)$var;
        foreach ($values as $k => &$v) {
            $subname = \sprintf('%s->%s', $name, $k);
            $subvalue = namespace\_process_value($v, $subname, $state, $k, $scope + 1);
            $cost += $subvalue->cost();
            $subvalues[] = $subvalue;
        }
        return new _Value(_ValueType::OBJECT, $name, $key, $var, $scope, $cost, $subvalues);
    }

    return new _Value(_ValueType::SCALAR, $name, $key, $var, $scope);
}


function _diff_values(_Value $from, _Value $to, _DiffState $state) {
    if (namespace\_lcs_values($from, $to, $lcs)) {
        namespace\_copy_value($state->diff, $from);
    }
    elseif (0 === $lcs) {
        namespace\_insert_value($state->diff, $to);
        namespace\_delete_value($state->diff, $from);
    }
    elseif (_ValueType::STRING === $from->type()) {
        $edit = namespace\_lcs_array($from->subvalues(), $to->subvalues());
        namespace\_build_diff_from_edit($from->subvalues(), $to->subvalues(), $edit, $state);
    }
    else {
        namespace\_copy_string($state->diff, namespace\_end_value($from));

        $edit = namespace\_lcs_array($from->subvalues(), $to->subvalues());
        namespace\_build_diff_from_edit($from->subvalues(), $to->subvalues(), $edit, $state);

        if ($from->key() === $to->key()) {
            namespace\_copy_string($state->diff, namespace\_start_value($from));
        }
        else {
            namespace\_insert_string($state->diff, namespace\_start_value($to));
            namespace\_delete_string($state->diff, namespace\_start_value($from));
        }
    }
}


function _lcs_values(_Value $from, _Value $to, &$lcs) {
    if (namespace\_compare_values($from, $to)) {
        $lcs = \max($from->cost(), $to->cost());
        return true;
    }

    $lcs = 0;
    if ($from->type() === $to->type()) {
        if (_ValueType::STRING === $from->type()) {
            if ($from->cost() > 1 || $to->cost() > 1) {
                $edit = namespace\_lcs_array($from->subvalues(), $to->subvalues());
                $lcs = $edit->m[$edit->flen][$edit->tlen];
            }
        }
        elseif (
            _ValueType::ARRAY === $from->type()
            || (_ValueType::OBJECT === $from->type()
                && \get_class($from->value()) === \get_class($to->value()))
        ) {
            $lcs = 1;
            if ($from->cost() > 1 && $to->cost() > 1) {
                $edit = namespace\_lcs_array($from->subvalues(), $to->subvalues());
                $lcs += $edit->m[$edit->flen][$edit->tlen];
            }
        }
    }
    return false;
}


function _lcs_array(array $from, array $to) {
    $m = array();
    $flen = \count($from);
    $tlen = \count($to);

    for ($f = 0; $f <= $flen; ++$f) {
        for ($t = 0; $t <= $tlen; ++$t) {
            if (!$f || !$t) {
                $m[$f][$t] = 0;
                continue;
            }

            $fvalue = $from[$f-1];
            $tvalue = $to[$t-1];
            if (namespace\_lcs_values($fvalue, $tvalue, $lcs)) {
                $lcs += $m[$f-1][$t-1];
            }
            else {
                $max = \max($m[$f-1][$t], $m[$f][$t-1]);
                if ($lcs) {
                    $max = \max($max, $m[$f-1][$t-1] + $lcs);
                }
                $lcs = $max;
            }
            $m[$f][$t] = $lcs;
        }
    }
    return new _Edit($flen, $tlen, $m);
}


function _compare_values(_Value $from, _Value $to) {
    return $from->key() === $to->key() && $from->value() === $to->value();
}


function _build_diff_from_edit(array $from, array $to, _Edit $edit, _DiffState $state) {
    $m = $edit->m;
    $f = $edit->flen;
    $t = $edit->tlen;

    while ($f || $t) {
        if ($f > 0 && $t > 0) {
            if (namespace\_compare_values($from[$f-1], $to[$t-1])) {
                if ($f === 1 && $t === 1) {
                    $pos = _DiffPosition::START;
                }
                elseif ($f === $edit->flen && $t === $edit->tlen) {
                    $pos = _DiffPosition::END;
                }
                else {
                    $pos = _DiffPosition::MIDDLE;
                }

                --$f;
                --$t;
                namespace\_copy_value($state->diff, $from[$f], $pos);
            }
            elseif ($m[$f-1][$t] < $m[$f][$t] && $m[$f][$t-1] < $m[$f][$t]) {
                --$f;
                --$t;
                namespace\_diff_values($from[$f], $to[$t], $state);
            }
            elseif ($m[$f][$t-1] >= $m[$f-1][$t]) {
                if ($t === 1) {
                    $pos = _DiffPosition::START;
                }
                elseif ($t === $edit->tlen) {
                    $pos = _DiffPosition::END;
                }
                else {
                    $pos = _DiffPosition::MIDDLE;
                }

                --$t;
                namespace\_insert_value($state->diff, $to[$t], $pos);
            }
            else {
                if ($f === 1) {
                    $pos = _DiffPosition::START;
                }
                elseif ($f === $edit->flen) {
                    $pos = _DiffPosition::END;
                }
                else {
                    $pos = _DiffPosition::MIDDLE;
                }

                --$f;
                namespace\_delete_value($state->diff, $from[$f], $pos);
            }
        }
        elseif ($f) {
            if ($f === 1) {
                $pos = _DiffPosition::START;
            }
            elseif ($f === $edit->flen) {
                $pos = _DiffPosition::END;
            }
            else {
                $pos = _DiffPosition::MIDDLE;
            }

            --$f;
            namespace\_delete_value($state->diff, $from[$f], $pos);
        }
        else {
            if ($t === 1) {
                $pos = _DiffPosition::START;
            }
            elseif ($t === $edit->tlen) {
                $pos = _DiffPosition::END;
            }
            else {
                $pos = _DiffPosition::MIDDLE;
            }

            --$t;
            namespace\_insert_value($state->diff, $to[$t], $pos);
        }
    }
}



/**
 * @param _DiffPosition::* $pos
 */
function _copy_value(array &$diff, _Value $value, $pos = _DiffPosition::NONE) {
    namespace\_copy_string($diff, namespace\_format_value($value, $pos));
}


/**
 * @param _DiffPosition::* $pos
 */
function _insert_value(array &$diff, _Value $value, $pos = _DiffPosition::NONE) {
    namespace\_insert_string($diff, namespace\_format_value($value, $pos));
}


/**
 * @param _DiffPosition::* $pos
 */
function _delete_value(array &$diff, _Value $value, $pos = _DiffPosition::NONE) {
    namespace\_delete_string($diff, namespace\_format_value($value, $pos));
}


function _copy_string(array &$diff, $string) {
    foreach (\array_reverse(\explode("\n", $string)) as $v) {
        $diff[] = new _DiffCopy($v);
    }
}


function _insert_string(array &$diff, $string) {
    foreach (\array_reverse(\explode("\n", $string)) as $v) {
        $diff[] = new _DiffInsert($v);
    }
}


function _delete_string(array &$diff, $string) {
    foreach (\array_reverse(\explode("\n", $string)) as $v) {
        $diff[] = new _DiffDelete($v);
    }
}


/**
 * @param _Value $value
 * @param _DiffPosition::* $pos
 * @return string
 */
function _format_value(_Value $value, $pos) {
    $indent = \str_repeat(namespace\_FORMAT_INDENT, $value->scope());
    $result = $indent;

    $key = $value->key();
    if ($key !== null) {
        $result .= "{$key} => ";
    }


    $type = $value->type();
    if (_ValueType::ARRAY === $type) {
        $seen = array('byval' => array(), 'byref' => array());
        $sentinels = array('byref' => null, 'byval' => new \stdClass());
        $result .= namespace\_format_array($value->value(), $value->name(), $seen, $sentinels, $indent);
    }
    elseif (_ValueType::OBJECT === $type) {
        $seen = array('byval' => array(), 'byref' => array());
        $sentinels = array('byref' => null, 'byval' => new \stdClass());
        $result .= namespace\_format_object($value->value(), $value->name(), $seen, $sentinels, $indent);
    }
    elseif (_ValueType::REFERENCE === $type) {
        $result .= $value->name();
    }
    elseif (_ValueType::RESOURCE === $type) {
        $result .= namespace\_format_resource($value->value());
    }
    elseif (_ValueType::STRING_PART === $type) {
        $formatted = \str_replace(array('\\', "'",), array('\\\\', "\\'",), $value->value());
        if (_DiffPosition::START === $pos) {
            $result .= "'{$formatted}";
        }
        elseif (_DiffPosition::MIDDLE === $pos) {
            $result .= $formatted;
        }
        elseif (_DiffPosition::END === $pos) {
            $result .= "{$formatted}'";
        }
        else {
            throw new \Exception("Unexpected position {$pos} for type STRING_PART");
        }
    }
    else {
        $result .= namespace\_format_scalar($value->value());
    }

    return $result;
}


/**
 * @param _Value $value
 * @return string
 */
function _start_value(_Value $value) {
    $type = $value->type();
    if (_ValueType::ARRAY === $type) {
        $result = \str_repeat(namespace\_FORMAT_INDENT, $value->scope());
        if ($value->key() !== null) {
            $result .= "{$value->key()} => ";
        }
        $result .= 'array(';
        return $result;
    }
    elseif (_ValueType::OBJECT === $type) {
        $result = \str_repeat(namespace\_FORMAT_INDENT, $value->scope());
        if ($value->key() !== null) {
            $result .= "{$value->key()} => ";
        }
        $result .= \get_class($value->value()) . ' {';
        return $result;
    }
    else {
        throw new \Exception(
            \sprintf('Trying to start non-container value of type %d', $type)
        );
    }
}


/**
 * @param _Value $value
 * @return string
 */
function _end_value(_Value $value) {
    $type = $value->type();
    if (_ValueType::ARRAY === $type) {
        return \str_repeat(namespace\_FORMAT_INDENT, $value->scope()) . ')';
    }
    elseif (_ValueType::OBJECT === $type) {
        return \str_repeat(namespace\_FORMAT_INDENT, $value->scope()) . '}';
    }
    else {
        throw new \Exception(
            \sprintf('Trying to end non-container value of type %d', $type)
        );
    }
}


function format_failure_message($assertion, $description = null, $detail = null) {
    $message = array();
    if ($assertion) {
        $message[] = $assertion;
    }
    if ($description) {
        $message[] = $description;
    }
    if (!$message) {
        $message[] = 'Assertion failed';
    }
    if ($detail) {
        $message[] = '';
        $message[] = $detail;
    }
    return \implode("\n", $message);
}



// Format a variable for display.
//
// This provides more readable(?) formatting of variables than PHP's built-in
// variable-printing functions (print_r(), var_dump(), var_export()) and also
// handles recursive references.

function format_variable(&$var) {
    $name = \is_object($var) ? \get_class($var) : \gettype($var);
    return namespace\_format_variable_indented($var, $name);
}


function _format_variable_indented(&$var, $name, $indent = '') {
    $seen = array('byval' => array(), 'byref' => array());
    // We'd really like to make this a constant static variable, but PHP won't
    // let us do that with an object instance. As a mitigation, we'll just
    // create the sentinels once at the start and then pass it around
    $sentinels = array('byref' => null, 'byval' => new \stdClass());
    return namespace\_format_recursive_variable($var, $name, $seen, $sentinels, $indent);
}


function _format_recursive_variable(&$var, $name, &$seen, $sentinels, $indent) {
    $reference = namespace\_check_reference($var, $name, $seen, $sentinels);
    if ($reference) {
        return $reference;
    }
    if (\is_scalar($var) || null === $var) {
        return namespace\_format_scalar($var);
    }
    if (\is_resource($var)) {
        return namespace\_format_resource($var);
    }
    if (\is_array($var)) {
        return namespace\_format_array($var, $name, $seen, $sentinels, $indent);
    }
    if (\is_object($var)) {
        return namespace\_format_object($var, $name, $seen, $sentinels, $indent);
    }
    throw new \Exception(
        \sprintf('Unexpected/unknown variable type: %s', \gettype($var))
    );
}


function _format_scalar(&$var) {
    return \var_export($var, true);
}


function _format_resource(&$var) {
    return \sprintf(
        '%s of type "%s"',
        \print_r($var, true),
        \get_resource_type($var)
    );
}


function _format_array(&$var, $name, &$seen, $sentinels, $padding) {
    $indent = $padding . namespace\_FORMAT_INDENT;
    $out = '';

    if ($var) {
        foreach ($var as $key => &$value) {
            $key = \var_export($key, true);
            $out .= \sprintf(
                "\n%s%s => %s",
                $indent,
                $key,
                namespace\_format_recursive_variable(
                    $value,
                    \sprintf('%s[%s]', $name, $key),
                    $seen,
                    $sentinels,
                    $indent
                )
            );
        }
        $out .= "\n$padding";
    }
    return "array($out)";
}


function _format_object(&$var, $name, &$seen, $sentinels, $padding) {
    $indent = $padding . namespace\_FORMAT_INDENT;
    $out = '';

    $class = \get_class($var);
    // #BC(7.1): use spl_object_hash instead of spl_object_id
    $id = \version_compare(\PHP_VERSION, '7.2', '<')
        ? \spl_object_hash($var)
        : \spl_object_id($var);
    $values = (array)$var;
    if ($values) {
        foreach ($values as $key => &$value) {
            // Object properties are cast to array keys as follows:
            //     public    $property -> "property"
            //     protected $property -> "\0*\0property"
            //     private   $property -> "\0class\0property"
            //         where "class" is the name of the class where the
            //         property is declared
            $key = \explode("\0", $key);
            $property = '$' . \array_pop($key);
            if ($key && $key[1] !== '*' && $key[1] !== $class) {
                $property = "$key[1]::$property";
            }
            $out .= \sprintf(
                "\n%s%s = %s;",
                $indent,
                $property,
                namespace\_format_recursive_variable(
                    $value,
                    \sprintf('%s->%s', $name, $property),
                    $seen,
                    $sentinels,
                    $indent
                )
            );
        }
        $out .= "\n$padding";
    }
    return "$class #$id {{$out}}";
}


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
function _check_reference(&$var, $name, &$seen, $sentinels) {
    if (\is_scalar($var) || \is_array($var) || null === $var) {
        $copy = $var;
        $var = $sentinels['byval'];
        $reference = \array_search($var, $seen['byval'], true);
        if (false === $reference) {
            $seen['byval'][$name] = &$var;
        }
        else {
            $reference = "&$reference";
        }
        $var = $copy;
    }
    else {
        $reference = \array_search($var, $seen['byref'], true);
        if (false === $reference) {
            $seen['byref'][$name] = &$var;
        }
        else {
            $copy = $var;
            $var = $sentinels['byref'];
            if ($var === $seen['byref'][$reference]) {
                $reference = "&$reference";
            }
            $var = $copy;
        }
    }
    return $reference;
}



function ksort_recursive(&$array, &$seen = array()) {
    if (!\is_array($array)) {
        return;
    }

    /* Prevent infinite recursion for arrays with recursive references. */
    $temp = $array;
    $array = null;
    $sorted = \in_array($array, $seen, true);
    $array = $temp;
    unset($temp);

    if (false !== $sorted) {
        return;
    }
    $seen[] = &$array;
    \ksort($array);
    foreach ($array as &$value) {
        namespace\ksort_recursive($value, $seen);
    }
}
