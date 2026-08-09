<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // JSON columns created by Laravel/MariaDB default to utf8mb4_bin collation.
        // LibreNMS requires all columns to use the database default (utf8mb4_unicode_ci).
        $alters = [
            "ALTER TABLE wmng_links MODIFY style longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL",
            "ALTER TABLE wmng_maps MODIFY options longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL",
            "ALTER TABLE wmng_nodes MODIFY meta longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL",
            "ALTER TABLE wmng_map_templates MODIFY config longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL",
        ];

        foreach ($alters as $sql) {
            DB::statement($sql);
        }
    }

    public function down(): void
    {
        $alters = [
            "ALTER TABLE wmng_links MODIFY style longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL",
            "ALTER TABLE wmng_maps MODIFY options longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL",
            "ALTER TABLE wmng_nodes MODIFY meta longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL",
            "ALTER TABLE wmng_map_templates MODIFY config longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL",
        ];

        foreach ($alters as $sql) {
            DB::statement($sql);
        }
    }
};
