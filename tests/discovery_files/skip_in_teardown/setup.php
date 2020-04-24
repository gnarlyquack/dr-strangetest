<?php

$this->log[] = __FILE__;

function teardown_directory_skip_in_teardown() {
    easytest\skip('Skip me');
}
