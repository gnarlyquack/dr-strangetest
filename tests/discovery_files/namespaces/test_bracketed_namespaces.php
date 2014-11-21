<?php

namespace ns1\ns1 {

$this->log[] = __FILE__;

class TestNamespace {}

/* ensure the namespace operator isn't confused for a namespace declaration */
const FOO = 'foo';
namespace\FOO;

}

namespace
    ns1 // parent namespace
    \   // namespace separator
    ns2 // sub namespace
{

class TestNamespace {}

const FOO = 'foo';
namespace\FOO;

}

namespace { // global namespace

class TestNamespace {}

const FOO = 'foo';
namespace\FOO;

}
