<?php

namespace LibreNMS\Plugins\WeathermapNG\Tests;

use PHPUnit\Framework\TestCase;
use LibreNMS\Plugins\WeathermapNG\Services\GridLayout;

class GridLayoutTest extends TestCase
{
    public function test_first_position_is_start_coordinates(): void
    {
        $grid = new GridLayout(100, 200, 50, 4);
        $pos = $grid->getNextPosition();

        $this->assertEquals(['x' => 100, 'y' => 200], $pos);
    }

    public function test_positions_fill_columns_left_to_right(): void
    {
        $grid = new GridLayout(0, 0, 100, 3);

        $this->assertEquals(['x' => 0, 'y' => 0], $grid->getNextPosition());
        $this->assertEquals(['x' => 100, 'y' => 0], $grid->getNextPosition());
        $this->assertEquals(['x' => 200, 'y' => 0], $grid->getNextPosition());
    }

    public function test_wraps_to_next_row_after_columns_filled(): void
    {
        $grid = new GridLayout(0, 0, 100, 2);

        $grid->getNextPosition(); // (0, 0)
        $grid->getNextPosition(); // (100, 0)
        $pos = $grid->getNextPosition(); // Should wrap to row 1

        $this->assertEquals(['x' => 0, 'y' => 100], $pos);
    }

    public function test_multiple_rows(): void
    {
        $grid = new GridLayout(10, 20, 50, 2);
        $positions = [];
        for ($i = 0; $i < 6; $i++) {
            $positions[] = $grid->getNextPosition();
        }

        $this->assertEquals(['x' => 10, 'y' => 20], $positions[0]);   // row 0, col 0
        $this->assertEquals(['x' => 60, 'y' => 20], $positions[1]);   // row 0, col 1
        $this->assertEquals(['x' => 10, 'y' => 70], $positions[2]);   // row 1, col 0
        $this->assertEquals(['x' => 60, 'y' => 70], $positions[3]);   // row 1, col 1
        $this->assertEquals(['x' => 10, 'y' => 120], $positions[4]);  // row 2, col 0
        $this->assertEquals(['x' => 60, 'y' => 120], $positions[5]);  // row 2, col 1
    }

    public function test_single_column_layout(): void
    {
        $grid = new GridLayout(50, 50, 80, 1);

        $this->assertEquals(['x' => 50, 'y' => 50], $grid->getNextPosition());
        $this->assertEquals(['x' => 50, 'y' => 130], $grid->getNextPosition());
        $this->assertEquals(['x' => 50, 'y' => 210], $grid->getNextPosition());
    }
}
