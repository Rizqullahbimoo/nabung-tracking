<?php

namespace App\Database;

use Illuminate\Database\Connectors\PostgresConnector;

/**
 * Postgres connector that appends libpq's "options" parameter to the DSN.
 *
 * Laravel's built-in PostgresConnector only knows about host/port/dbname/
 * sslmode/etc. Neon's serverless Postgres needs the endpoint id passed through
 * the libpq "options" parameter when the local libpq is too old to send it via
 * TLS SNI (the "Endpoint ID is not specified ... upgrade libpq for SNI" error).
 *
 * Set DB_OPTIONS in .env, e.g. DB_OPTIONS="endpoint=ep-xxxx-xxxx".
 */
class PostgresConnectorWithOptions extends PostgresConnector
{
    protected function getDsn(array $config)
    {
        $dsn = parent::getDsn($config);

        if (! empty($config['libpq_options'])) {
            $dsn .= ';options='.$config['libpq_options'];
        }

        return $dsn;
    }
}
