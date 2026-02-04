<?php

/**
 * Добавить в config/database.php в массив 'connections':
 *
 * 'sqlite_testing' => [
 *     'driver' => 'sqlite',
 *     'database' => ':memory:',
 *     'prefix' => '',
 *     'foreign_key_constraints' => true,
 * ],
 */

return [
    'sqlite_testing' => [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ],
];
