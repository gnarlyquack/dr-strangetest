<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.


function resolve_function($name)
{
    $default_namespace = 'example\\';
    $result = strangetest\resolve_test_name($name, $default_namespace);
    return $result;
}


function resolve_method($name)
{
    $default_namespace = 'example\\';
    $default_class = 'example\\Example';
    $result = strangetest\resolve_test_name($name, $default_namespace, $default_class);
    return $result;
}


function test_qualified_function_resolves_to_itself()
{
    $name = 'foo\\bar';
    strangetest\assert_identical('foo\\bar', resolve_function($name));
    strangetest\assert_identical('foo\\bar', resolve_method($name));
}


function test_unqualified_function_resolves_to_current_class_or_namespace()
{
    $name = 'bar';
    strangetest\assert_identical('example\\bar', resolve_function($name));
    strangetest\assert_identical('example\\Example::bar', resolve_method($name));
}


function test_globally_namespaced_function_resolves_to_global_namespace()
{
    $name = '\\bar';
    strangetest\assert_identical('bar', resolve_function($name));
    strangetest\assert_identical('bar', resolve_method($name));
}


function test_qualified_method_resolves_to_itself()
{
    $name = 'foo\\Foo::bar';
    strangetest\assert_identical('foo\\Foo::bar', resolve_function($name));
    strangetest\assert_identical('foo\\Foo::bar', resolve_method($name));
}


function test_unqualified_method_resolves_to_current_namespace()
{
    $name = 'Foo::bar';
    strangetest\assert_identical('example\\Foo::bar', resolve_function($name));
    strangetest\assert_identical('example\\Foo::bar', resolve_method($name));
}


function test_globally_namespaced_method_resolves_to_global_namespace()
{
    $name = '\\Foo::bar';
    strangetest\assert_identical('Foo::bar', resolve_function($name));
    strangetest\assert_identical('Foo::bar', resolve_method($name));
}


function test_empty_class_resolves_to_function_in_current_namespace()
{
    $name = '::bar';
    strangetest\assert_identical('example\\bar', resolve_function($name));
    strangetest\assert_identical('example\\bar', resolve_method($name));
}


function test_globally_namespaced_name_with_empty_class_resolves_to_global_function()
{
    $name = '\::bar';
    strangetest\assert_identical('bar', resolve_function($name));
    strangetest\assert_identical('bar', resolve_method($name));
}
