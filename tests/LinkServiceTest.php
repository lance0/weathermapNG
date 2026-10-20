<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;
use LibreNMS\Plugins\WeathermapNG\Services\LinkService;

class LinkServiceTest extends TestCase
{
    /**
     * validateLinkData() is a pure guard: links with ports must name both
     * endpoints. Behavioral coverage via reflection (no DB needed).
     */
    private function invokeValidate(array $data): void
    {
        $service = new LinkService();
        $reflection = new \ReflectionMethod($service, 'validateLinkData');
        $reflection->setAccessible(true);
        $reflection->invoke($service, $data);
    }

    public function test_link_without_ports_needs_no_nodes(): void
    {
        $this->expectNotToPerformAssertions();
        $this->invokeValidate(['src_node_id' => 1]);
    }

    public function test_link_with_port_but_missing_nodes_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Both source and destination nodes are required when using ports');

        $this->invokeValidate(['port_id_a' => 10, 'dst_node_id' => 2]);
    }

    public function test_link_with_ports_and_both_nodes_passes(): void
    {
        $this->expectNotToPerformAssertions();
        $this->invokeValidate(['port_id_a' => 10, 'port_id_b' => 20, 'src_node_id' => 1, 'dst_node_id' => 2]);
    }

    public function test_link_with_only_port_b_and_missing_src_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->invokeValidate(['port_id_b' => 20, 'dst_node_id' => 2]);
    }

    public function test_link_with_zero_port_ids_treated_as_no_ports(): void
    {
        // port_id_a of 0 is falsy — guard should not fire.
        $this->expectNotToPerformAssertions();
        $this->invokeValidate(['port_id_a' => 0]);
    }
}
