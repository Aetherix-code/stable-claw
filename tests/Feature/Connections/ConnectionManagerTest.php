<?php

use App\Models\Connection;
use App\Services\Connections\ConnectionManager;
use App\Services\Connections\Mail\GmailConnector;

test('resolves gmail connector for mail connection', function () {
    $connection = Connection::factory()->make();

    $manager = new ConnectionManager;
    $connector = $manager->resolveMailConnector($connection);

    expect($connector)->toBeInstanceOf(GmailConnector::class);
});

test('throws exception for unknown provider', function () {
    $connection = Connection::factory()->make(['provider' => 'unknown']);

    $manager = new ConnectionManager;
    $manager->resolveMailConnector($connection);
})->throws(InvalidArgumentException::class, 'No connector registered');

test('throws exception for unknown type', function () {
    $connection = Connection::factory()->make(['type' => 'unknown']);

    $manager = new ConnectionManager;
    $manager->resolveMailConnector($connection);
})->throws(InvalidArgumentException::class, 'No connector registered');
