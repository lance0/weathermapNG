<?php

namespace LibreNMS\Plugins\WeathermapNG\Services;

/**
 * Simple grid layout utility for auto-discovered nodes
 */
class GridLayout
{
    private int $startX;
    private int $startY;
    private int $step;
    private int $columns;
    private int $currentIndex = 0;

    public function __construct(int $startX, int $startY, int $step, int $columns)
    {
        $this->startX = $startX;
        $this->startY = $startY;
        $this->step = $step;
        $this->columns = $columns;
    }

    public function getNextPosition(): array
    {
        $row = intdiv($this->currentIndex, $this->columns);
        $col = $this->currentIndex % $this->columns;

        $x = $this->startX + ($col * $this->step);
        $y = $this->startY + ($row * $this->step);

        $this->currentIndex++;

        return ['x' => $x, 'y' => $y];
    }
}
