<?php

// lib/Link.php
namespace LibreNMS\Plugins\WeathermapNG;

class Link
{
    private $id;
    private $sourceNode;
    private $targetNode;
    private $bandwidth;
    private $label;
    private $config;
    private $utilization;

    public function __construct($id, Node $source, Node $target, $config = [])
    {
        $this->id = $id;
        $this->sourceNode = $source;
        $this->targetNode = $target;
        $this->config = $config;
        $this->bandwidth = $config['bandwidth'] ?? 1000000000; // 1Gbps default
        $this->label = $config['label'] ?? '';
        $this->utilization = 0;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getSourceId()
    {
        return $this->sourceNode->getId();
    }

    public function getTargetId()
    {
        return $this->targetNode->getId();
    }

    public function getSourceNode()
    {
        return $this->sourceNode;
    }

    public function getTargetNode()
    {
        return $this->targetNode;
    }

    public function getBandwidth()
    {
        return $this->bandwidth;
    }

    public function getLabel()
    {
        return $this->label;
    }

    public function getUtilization()
    {
        return $this->utilization;
    }

    public function setUtilization($utilization)
    {
        $this->utilization = max(0, min(1, $utilization)); // Clamp between 0 and 1
    }

    public function calculateUtilization()
    {
        $sourceValue = $this->sourceNode->getCurrentValue();
        $targetValue = $this->targetNode->getCurrentValue();

        if ($sourceValue === null && $targetValue === null) {
            $this->utilization = 0;
            return;
        }

        // Use the higher of the two values (bidirectional link)
        $maxValue = max($sourceValue ?? 0, $targetValue ?? 0);

        if ($this->bandwidth > 0) {
            $this->utilization = $maxValue / $this->bandwidth;
        } else {
            $this->utilization = 0;
        }
    }

    public function getStatus()
    {
        $sourceStatus = $this->sourceNode->getStatus();
        $targetStatus = $this->targetNode->getStatus();

        if ($sourceStatus === 'down' || $targetStatus === 'down') {
            return 'down';
        }

        if ($sourceStatus === 'unknown' || $targetStatus === 'unknown') {
            return 'unknown';
        }

        if ($this->utilization > 0.9) {
            return 'critical';
        }

        if ($this->utilization > 0.7) {
            return 'warning';
        }

        return 'normal';
    }

    public function getColor()
    {
        $status = $this->getStatus();

        $colors = [
            'normal' => config('weathermapng.colors.link_normal', '#28a745'),
            'warning' => config('weathermapng.colors.link_warning', '#ffc107'),
            'critical' => config('weathermapng.colors.link_critical', '#dc3545'),
            'down' => config('weathermapng.colors.node_down', '#dc3545'),
            'unknown' => config('weathermapng.colors.node_unknown', '#6c757d'),
        ];

        return $colors[$status] ?? $colors['unknown'];
    }

    public function getWidth()
    {
        $baseWidth = config('weathermapng.rendering.link_width', 2);
        $maxWidth = $baseWidth * 3;

        return min($maxWidth, $baseWidth + ($this->utilization * $baseWidth * 2));
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'source' => $this->getSourceId(),
            'target' => $this->getTargetId(),
            'bandwidth' => $this->bandwidth,
            'label' => $this->label,
            'utilization' => $this->utilization,
            'status' => $this->getStatus(),
            'color' => $this->getColor(),
            'width' => $this->getWidth()
        ];
    }
}
