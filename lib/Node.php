<?php

// lib/Node.php
namespace LibreNMS\Plugins\WeathermapNG;

class Node
{
    private $id;
    private $label;
    private $position;
    private $deviceId;
    private $interfaceId;
    private $metric;
    private $data;
    private $status;
    private $config;

    public function __construct($id, $config = [])
    {
        $this->id = $id;
        $this->config = $config;
        $this->label = $config['label'] ?? $id;
        $this->position = [
            'x' => $config['x'] ?? 0,
            'y' => $config['y'] ?? 0
        ];
        $this->deviceId = $config['device_id'] ?? null;
        $this->interfaceId = $config['interface_id'] ?? null;
        $this->metric = $config['metric'] ?? 'traffic_in';
        $this->data = [];
        $this->status = 'unknown';
    }

    public function getId()
    {
        return $this->id;
    }

    public function getLabel()
    {
        return $this->label;
    }

    public function getPosition()
    {
        return $this->position;
    }

    public function getDeviceId()
    {
        return $this->deviceId;
    }

    public function getInterfaceId()
    {
        return $this->interfaceId;
    }

    public function getMetric()
    {
        return $this->metric;
    }

    public function getData()
    {
        return $this->data;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    public function setStatus($status)
    {
        $this->status = $status;
    }

    public function hasDataSource()
    {
        return $this->deviceId && $this->interfaceId;
    }

    public function getCurrentValue()
    {
        if (empty($this->data)) {
            return null;
        }

        $lastEntry = end($this->data);
        return $lastEntry['value'] ?? null;
    }

    public function getAverageValue()
    {
        if (empty($this->data)) {
            return null;
        }

        $sum = 0;
        $count = 0;

        foreach ($this->data as $entry) {
            if (isset($entry['value']) && $entry['value'] > 0) {
                $sum += $entry['value'];
                $count++;
            }
        }

        return $count > 0 ? $sum / $count : null;
    }

    public function getMaxValue()
    {
        if (empty($this->data)) {
            return null;
        }

        $max = 0;
        foreach ($this->data as $entry) {
            if (isset($entry['value']) && $entry['value'] > $max) {
                $max = $entry['value'];
            }
        }

        return $max;
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'position' => $this->position,
            'device_id' => $this->deviceId,
            'interface_id' => $this->interfaceId,
            'metric' => $this->metric,
            'status' => $this->status,
            'current_value' => $this->getCurrentValue(),
            'average_value' => $this->getAverageValue(),
            'max_value' => $this->getMaxValue(),
            'data_points' => count($this->data)
        ];
    }
}
