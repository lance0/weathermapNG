#!/usr/bin/env php
<?php
// Compatibility entrypoint: legacy JSON backups were incomplete and unsafe to restore.
fwrite(STDERR, "The legacy backup command is retired; no files or database rows were changed.\nUse full database/config backups: https://github.com/lance0/weathermapNG/blob/main/docs/INSTALL.md#backup-and-recovery\nExisting backup files are preserved.\n");
exit(1);
