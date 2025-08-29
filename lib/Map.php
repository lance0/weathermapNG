<?php
// lib/Map.php
namespace LibreNMS\Plugins\WeathermapNG;

class Map
{
    private $id;
    private $nodes = [];
    private $links = [];
    private $config;
    private $width;
    private $height;
    private $title;

    public function __construct($configFile = null)
    {
        if ($configFile && file_exists($configFile)) {
            $this->loadFromFile($configFile);
        } else {
            $this->config = [
                'global' => [
                    'width' => config('weathermapng.default_width', 800),
                    'height' => config('weathermapng.default_height', 600),
                    'title' => 'Network Map'
                ]
            ];
        }

        $this->width = $this->config['global']['width'] ?? 800;
        $this->height = $this->config['global']['height'] ?? 600;
        $this->title = $this->config['global']['title'] ?? 'Network Map';
    }

    public function loadFromFile($configFile)
    {
        $this->config = parse_ini_file($configFile, true);
        $this->id = basename($configFile, '.conf');

        // Parse nodes
        foreach ($this->config as $section => $data) {
            if (strpos($section, 'node:') === 0) {
                $nodeId = substr($section, 5);
                $this->nodes[$nodeId] = new Node($nodeId, $data);
            }
        }

        // Parse links
        foreach ($this->config as $section => $data) {
            if (strpos($section, 'link:') === 0) {
                $linkId = substr($section, 5);
                if (isset($data['nodes'])) {
                    $nodeIds = explode(' ', $data['nodes']);
                    if (count($nodeIds) >= 2) {
                        $sourceId = $nodeIds[0];
                        $targetId = $nodeIds[1];

                        if (isset($this->nodes[$sourceId]) && isset($this->nodes[$targetId])) {
                            $this->links[$linkId] = new Link(
                                $linkId,
                                $this->nodes[$sourceId],
                                $this->nodes[$targetId],
                                $data
                            );
                        }
                    }
                }
            }
        }
    }

    public function addNode(Node $node)
    {
        $this->nodes[$node->getId()] = $node;
    }

    public function addLink(Link $link)
    {
        $this->links[$link->getId()] = $link;
    }

    public function getNodes()
    {
        return $this->nodes;
    }

    public function getLinks()
    {
        return $this->links;
    }

    public function getNode($id)
    {
        return $this->nodes[$id] ?? null;
    }

    public function getLink($id)
    {
        return $this->links[$id] ?? null;
    }

    public function getWidth()
    {
        return $this->width;
    }

    public function getHeight()
    {
        return $this->height;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function getId()
    {
        return $this->id;
    }

    public function loadData()
    {
        foreach ($this->nodes as $node) {
            if ($node->hasDataSource()) {
                $data = DataSource::getRRDData(
                    $node->getDeviceId(),
                    $node->getInterfaceId(),
                    $node->getMetric()
                );
                $node->setData($data);

                // Set node status
                $status = DataSource::getInterfaceStatus($node->getInterfaceId());
                $node->setStatus($status);
            }
        }

        // Calculate link utilization
        foreach ($this->links as $link) {
            $link->calculateUtilization();
        }
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'width' => $this->width,
            'height' => $this->height,
            'nodes' => array_map(function($node) {
                return $node->toArray();
            }, $this->nodes),
            'links' => array_map(function($link) {
                return $link->toArray();
            }, $this->links),
            'metadata' => [
                'total_nodes' => count($this->nodes),
                'total_links' => count($this->links),
                'last_updated' => date('c')
            ]
        ];
    }

    public function toJson()
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }

    public function saveToFile($configFile)
    {
        $content = "[global]\n";
        $content .= "width {$this->width}\n";
        $content .= "height {$this->height}\n";
        $content .= "title \"{$this->title}\"\n\n";

        foreach ($this->nodes as $node) {
            $content .= "[node:{$node->getId()}]\n";
            $content .= "label \"{$node->getLabel()}\"\n";
            $content .= "x {$node->getPosition()['x']}\n";
            $content .= "y {$node->getPosition()['y']}\n";

            if ($node->getDeviceId()) {
                $content .= "device_id {$node->getDeviceId()}\n";
            }

            if ($node->getInterfaceId()) {
                $content .= "interface_id {$node->getInterfaceId()}\n";
            }

            $content .= "metric {$node->getMetric()}\n\n";
        }

        foreach ($this->links as $link) {
            $content .= "[link:{$link->getId()}]\n";
            $content .= "nodes {$link->getSourceId()} {$link->getTargetId()}\n";
            $content .= "bandwidth {$link->getBandwidth()}\n";

            if ($link->getLabel()) {
                $content .= "label \"{$link->getLabel()}\"\n";
            }

            $content .= "\n";
        }

        file_put_contents($configFile, $content);
    }
}