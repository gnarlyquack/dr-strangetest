<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace easytest;


final class Reporter implements IReporter {
    private $count = [
        'Pass' => 0,
        'Error' => 0,
        'Failure' => 0,
        'Output' => 0,
        'Skip' => 0,
    ];
    private $progress = [
        'Pass' => '.',
        'Error' => 'E',
        'Failure' => 'F',
        'Skip' => 'S',
        'Output' => 'O',
    ];
    private $summary = [
        'Error' => 'Errors',
        'Failure' => 'Failures',
        'Output' => 'Output',
        'Skip' => 'Skips',
    ];
    private $results = [];

    private $quiet;


    public function __construct($header, $quiet) {
        $this->quiet = $quiet;
        echo "$header\n\n";
    }

    public function render_report() {
        $output = 0;
        foreach ($this->results as $result) {
            list($type, $source, $message) = $result;
            if ('Output' === $type) {
                ++$output;
            }
            \printf(
                "\n\n%s\n%s: %s\n%s\n%s",
                \str_repeat('=', 70),
                \strtoupper($type),
                $source,
                \str_repeat('-', 70),
                $message
            );
        }

        if (!$counts = \array_filter($this->count)) {
            echo "No tests found!\n";
            return false;
        }

        echo "\n\n\n";

        if ($this->quiet) {
            $suppressed = [];
            if ($output !== $this->count['Output']) {
                $suppressed[] = 'output';
            }
            if ($this->count['Skip']) {
                $suppressed[] = 'skipped tests';
            }
            if ($suppressed) {
                \printf(
                    "This report omitted %s.\nTo view, rerun easytest with the --verbose option.\n\n",
                    \implode(' and ', $suppressed)
                );
            }
        }

        echo "Tests: ", $this->count['Pass'] + $this->count['Failure'];
        unset($counts['Pass']);
        foreach ($counts as $type => $count) {
            \printf(', %s: %d', $this->summary[$type], $count);
        }
        echo "\n";

        return !($this->count['Failure'] || $this->count['Error']);
    }

    public function report_success() {
        $this->update_report('Pass');
    }

    public function report_error($source, $message) {
        $this->update_report('Error', $source, $message);
    }

    public function report_failure($source, $message) {
        $this->update_report('Failure', $source, $message);
    }

    public function report_skip($source, $message) {
        if ($this->quiet) {
            $source = null;
            $message = null;
        }
        $this->update_report('Skip', $source, $message);
    }

    public function buffer($source, callable $callback) {
        $levels = \ob_get_level();
        \ob_start();

        try {
            $result = $callback();
        }
        catch (\Throwable $e) {}
        // #BC(5.6): Catch Exception, which implements Throwable
        catch (\Exception $e) {}

        $buffers = [];
        while (($level = \ob_get_level()) > $levels) {
            if ($buffer = \trim(\ob_get_clean())) {
                $buffers[$level - $levels] = $buffer;
            }
        }

        if ($this->quiet && !isset($e)) {
            if ($buffers) {
                $this->update_report('Output');
            }
            return $result;
        }

        switch (\count($buffers)) {
        case 0:
            /* do nothing */
            break;

        case 1:
            $this->update_report('Output', $source, \current($buffers));
            break;

        default:
            $output = '';
            foreach (\array_reverse($buffers, true) as $i => $buffer) {
                $output .= \sprintf(
                    "%s\n%s\n\n",
                    \str_pad(" Buffer $i ", 70, '~', \STR_PAD_BOTH),
                    $buffer
                );
            }
            $this->update_report('Output', $source, \rtrim($output));
            break;
        }

        if (isset($e)) {
            throw $e;
        }
        else {
            return $result;
        }
    }

    private function update_report($type, $source = null, $message = null) {
        ++$this->count[$type];
        echo $this->progress[$type];
        if ($source && $message) {
            $this->results[] = [$type, $source, $message];
        }
    }
}
