<?php

namespace App\Services\Connections;

use App\Models\Connection;
use App\Services\Connections\Contracts\MailConnector;
use App\Services\Connections\Mail\GmailConnector;
use InvalidArgumentException;

class ConnectionManager
{
    /**
     * @var array<string, array<string, class-string>>
     */
    private array $connectors = [
        'mail' => [
            'gmail' => GmailConnector::class,
        ],
    ];

    public function resolveMailConnector(Connection $connection): MailConnector
    {
        $class = $this->connectors[$connection->type][$connection->provider] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException(
                "No connector registered for type [{$connection->type}] provider [{$connection->provider}]."
            );
        }

        $connector = new $class($connection);

        if (! $connector instanceof MailConnector) {
            throw new InvalidArgumentException(
                "Connector [{$class}] does not implement MailConnector."
            );
        }

        return $connector;
    }
}
