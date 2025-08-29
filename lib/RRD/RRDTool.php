<?php
// lib/RRD/RRDTool.php
namespace LibreNMS\Plugins\WeathermapNG\RRD;

class RRDTool
{
    private $rrdtoolPath;

    public function __construct()
    {
        $this->rrdtoolPath = config('weathermapng.rrdtool_path', '/usr/bin/rrdtool');

        // Try common rrdtool locations if not found
        if (!file_exists($this->rrdtoolPath)) {
            $commonPaths = [
                '/usr/bin/rrdtool',
                '/usr/local/bin/rrdtool',
                '/opt/rrdtool/bin/rrdtool',
                'rrdtool' // hope it's in PATH
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
        $returnVal = shell_exec("which $command 2>/dev/null");
        return !empty($returnVal);
    }

    public function fetch($rrdPath, $metric, $period = '1h')
    {
        if (!file_exists($rrdPath)) {
            return [];
        }

        $start = strtotime("-{$period}");
        $end = time();

        $command = sprintf(
            '%s fetch %s AVERAGE -s %d -e %d 2>/dev/null',
            escapeshellcmd($this->rrdtoolPath),
            escapeshellarg($rrdPath),
            $start,
            $end
        );

        $output = shell_exec($command);

        if ($output === null || $output === false) {
            return [];
        }

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

            // Skip error messages
            if (stripos($line, 'error') !== false || stripos($line, 'rrdtool') === 0) {
                continue;
            }

            // Parse header to find metric index
            if (!$headerParsed && strpos($line, ':') === false) {
                $headers = preg_split('/\s+/', $line);
                $metricIndex = $this->getMetricIndex($metric, $headers);
                $headerParsed = true;
                continue;
            }

            // Parse data lines
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

                // Handle various "no data" representations
                if ($value !== null &&
                    $value !== 'nan' &&
                    $value !== 'NAN' &&
                    $value !== 'U' &&
                    $value !== '-nan' &&
                    is_numeric($value)) {
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

        return 0; // Default to first column
    }

    public function getLastValue($rrdPath, $metric)
    {
        $data = $this->fetch($rrdPath, $metric, '5m'); // Last 5 minutes

        if (empty($data)) {
            return null;
        }

        // Return the most recent value
        $lastEntry = end($data);
        return $lastEntry['value'];
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
            if ($entry['value'] > 0) { // Only count positive values
                $sum += $entry['value'];
                $count++;
            }
        }

        return $count > 0 ? $sum / $count : null;
    }
}