<?php

namespace App\Database\Connectors;

use Illuminate\Database\Connectors\PostgresConnector;

/**
 * Limits how long libpq may wait while opening a PostgreSQL connection.
 *
 * The default can exceed the mobile client's HTTP timeout when the database
 * host is unreachable, which makes a simple login appear to hang.
 */
class TimedPostgresConnector extends PostgresConnector
{
    protected function getDsn(array $config)
    {
        return parent::getDsn($config).';connect_timeout='.(int) ($config['connect_timeout'] ?? 5);
    }
}
