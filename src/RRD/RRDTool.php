<?php

// lib/RRD/RRDTool.php
namespace LibreNMS\Plugins\WeathermapNG\RRD;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class RRDTool
{
    private $rrdtoolPath;

    public function __construct()
    {
        $this->rrdtoolPath = config('weathermapng.rrdtool_path', '/usr/bin/rrdtool');

        if (!file_exists($this->rrdtoolPath)) {
            $commonPaths = [
                '/usr/bin/rrdtool',
                '/usr/local/bin/rrdtool',
                '/opt/rrdtool/bin/rrdtool',
                'rrdtool'
            ];

            foreach ($commonPaths as $path) {
                if (file_exists($path) || $this->commandExists($path)) {
                    $this->rrdtoolPath = $path;
                    break;
                }
            }
        }
    }

    private function commandExists($command)
    {
        $process = new Process(['which', $command]);
        $process->run();

        return $process->isSuccessful();
    }

    public function fetch($rrdPath, $metric, $period = '1h')
    {
        if (!file_exists($rrdPath)) {
            return [];
        }

        $start = strtotime("-{$period}");
        $end = time();

        $command = [
            $this->rrdtoolPath,
            'fetch',
            $rrdPath,
            'AVERAGE',
            '-s',
            (string)$start,
            '-e',
            (string)$end,
        ];

        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            return [];
        }

        $output = $process->getOutput();
        return $this->parseRRDOutput($output, $metric);
    }

    private function parseRRDOutput($output, $metric)
    {
        $lines = explode("\n", trim($output));
        $data = [];
        $headerParsed = false;
        $metricIndex = 0;

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            if (stripos($line, 'error') !== false || stripos($line, 'rrdtool') === 0) {
                continue;
            }

            if (!$headerParsed && strpos($line, ':') === false) {
                $headers = preg_split('/\s+/', $line);
                $metricIndex = $this->getMetricIndex($metric, $headers);
                $headerParsed = true;
                continue;
            }

            if (strpos($line, ':') !== false) {
                $parts = explode(':', $line, 2);
                if (count($parts) !== 2) {
                    continue;
                }

                list($timestamp, $values) = $parts;
                $timestamp = trim($timestamp);
                $values = trim($values);

                if (!is_numeric($timestamp)) {
                    continue;
                }

                $valueArray = preg_split('/\s+/', $values);
                $value = isset($valueArray[$metricIndex]) ? trim($valueArray[$metricIndex]) : null;

                if (
                    $value !== null &&
                    $value !== 'nan' &&
                    $value !== 'NAN' &&
                    $value !== 'U' &&
                    $value !== '-nan' &&
                    is_numeric($value)
                ) {
                    $data[] = [
                        'timestamp' => (int)$timestamp,
                        'value' => (float)$value
                    ];
                }
            }
        }

        return $data;
    }

    private function getMetricIndex($metric, $headers)
    {
        $metricMap = [
            'traffic_in' => ['traffic_in', 'INOCTETS'],
            'traffic_out' => ['traffic_out', 'OUTOCTETS'],
            'packets_in' => ['packets_in', 'INPKTS'],
            'packets_out' => ['packets_out', 'OUTPKTS'],
            'errors_in' => ['errors_in', 'INERRORS'],
            'errors_out' => ['errors_out', 'OUTERRORS'],
        ];

        $possibleNames = $metricMap[$metric] ?? [$metric];

        foreach ($possibleNames as $name) {
            $index = array_search($name, $headers);
            if ($index !== false) {
                return $index;
            }
        }

        return 0;
    }

    public function getLastValue($rrdPath, $metric)
    {
        $data = $this->fetch($rrdPath, $metric, '5m');

        if (empty($data)) {
            return null;
        }

        $lastEntry = end($data);
        return $lastEntry['value'];
    }

    /**
     * Fetch the last row of every column from an RRD file in a single rrdtool invocation.
     *
     * Returns an associative array mapping canonical metric names (traffic_in,
     * traffic_out, …) to their last numeric value, or null when unavailable.
     *
     * @return array<string, float|null>
     */
    public function getLastValues($rrdPath): array
    {
        $row = $this->fetchLastRow($rrdPath, '5m');

        if (empty($row)) {
            return [];
        }

        $result = [];
        foreach ($this->allKnownMetrics() as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                if (array_key_exists($alias, $row) && $this->isValidValue($row[$alias])) {
                    $result[$canonical] = (float) $row[$alias];
                    continue 2;
                }
            }
        }

        return $result;
    }

    /**
     * Run rrdtool fetch once and return the last data row keyed by column header.
     *
     * @return array<string, string|null>
     */
    private function fetchLastRow($rrdPath, $period = '1h')
    {
        if (!file_exists($rrdPath)) {
            return [];
        }

        $start = strtotime("-{$period}");
        $end = time();

        $command = [
            $this->rrdtoolPath,
            'fetch',
            $rrdPath,
            'AVERAGE',
            '-s',
            (string)$start,
            '-e',
            (string)$end,
        ];

        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            return [];
        }

        return $this->parseLastRow($process->getOutput());
    }

    /**
     * Parse rrdtool fetch output and return the last valid data row keyed by header.
     *
     * @return array<string, string|null>
     */
    private function parseLastRow($output): array
    {
        $lines = explode("\n", trim($output));
        $headers = [];
        $headerParsed = false;
        $lastRow = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            if (stripos($line, 'error') !== false || stripos($line, 'rrdtool') === 0) {
                continue;
            }

            if (!$headerParsed && strpos($line, ':') === false) {
                $headers = preg_split('/\s+/', $line);
                $headerParsed = true;
                continue;
            }

            if (strpos($line, ':') !== false) {
                $parts = explode(':', $line, 2);
                if (count($parts) !== 2) {
                    continue;
                }

                [$timestamp, $values] = $parts;
                $timestamp = trim($timestamp);
                $values = trim($values);

                if (!is_numeric($timestamp)) {
                    continue;
                }

                $valueArray = preg_split('/\s+/', $values);
                $row = [];
                foreach ($headers as $i => $name) {
                    $row[$name] = isset($valueArray[$i]) ? trim($valueArray[$i]) : null;
                }
                $lastRow = $row;
            }
        }

        return $lastRow;
    }

    /**
     * @return array<string, array<string>>
     */
    private function allKnownMetrics(): array
    {
        return [
            'traffic_in' => ['traffic_in', 'INOCTETS'],
            'traffic_out' => ['traffic_out', 'OUTOCTETS'],
            'packets_in' => ['packets_in', 'INPKTS'],
            'packets_out' => ['packets_out', 'OUTPKTS'],
            'errors_in' => ['errors_in', 'INERRORS'],
            'errors_out' => ['errors_out', 'OUTERRORS'],
        ];
    }

    private function isValidValue($value): bool
    {
        return $value !== null
            && $value !== 'nan'
            && $value !== 'NAN'
            && $value !== 'U'
            && $value !== '-nan'
            && is_numeric($value);
    }

    public function getAverageValue($rrdPath, $metric, $period = '1h')
    {
        $data = $this->fetch($rrdPath, $metric, $period);

        if (empty($data)) {
            return null;
        }

        $sum = 0;
        $count = 0;

        foreach ($data as $entry) {
            if ($entry['value'] > 0) {
                $sum += $entry['value'];
                $count++;
            }
        }

        return $count > 0 ? $sum / $count : null;
    }
}
