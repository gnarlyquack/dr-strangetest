<?php

namespace ns2;

$this->log[] = __FILE__;

class TestNamespace {}

/* ensure the namespace operator isn't confused for a namespace declaration */
const FOO = 'foo';
namespace\FOO;


namespace/* Yup, this is valid! */
         // as is this!
         ns3;

class TestNamespace {}
