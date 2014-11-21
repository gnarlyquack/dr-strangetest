<?php

$this->log[] = __FILE__;

return function($test) {
    $this->log[] = sprintf('%s loading %s', __FILE__, $test);
    return new $test();
};
