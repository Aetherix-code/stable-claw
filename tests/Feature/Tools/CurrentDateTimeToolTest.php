<?php

use App\Services\Tools\CurrentDateTimeTool;
use Illuminate\Support\Facades\Date;

test('it returns current date and time in default timezone', function () {
    Date::setTestNow('2026-04-10 14:30:00');

    $tool = new CurrentDateTimeTool;
    $result = $tool->execute([]);

    expect($result->success)->toBeTrue();
    expect($result->data)
        ->datetime->toBe('2026-04-10 14:30:00')
        ->date->toBe('2026-04-10')
        ->time->toBe('14:30:00')
        ->day_of_week->toBe('Friday')
        ->unix_timestamp->toBeInt();
});

test('it returns date and time in a specific timezone', function () {
    Date::setTestNow('2026-04-10 14:30:00');

    $tool = new CurrentDateTimeTool;
    $result = $tool->execute(['timezone' => 'America/New_York']);

    expect($result->success)->toBeTrue();
    expect($result->data)->timezone->toBe('America/New_York');
});

test('it returns error for invalid timezone', function () {
    $tool = new CurrentDateTimeTool;
    $result = $tool->execute(['timezone' => 'Invalid/Timezone']);

    expect($result->success)->toBeFalse();
    expect($result->error)->not->toBeEmpty();
});
