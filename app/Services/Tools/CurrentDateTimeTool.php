<?php

namespace App\Services\Tools;

use App\Services\Tools\Contracts\Tool;
use App\Services\Tools\DTOs\ToolResult;
use Illuminate\Support\Facades\Date;

class CurrentDateTimeTool extends Tool
{
    public function name(): string
    {
        return 'current_date_time';
    }

    public function description(): string
    {
        return 'Get the current date and time, optionally in a specific timezone.';
    }

    public function parameters(): array
    {
        return [
            'timezone' => [
                'type' => 'string',
                'description' => 'IANA timezone (e.g. "America/New_York", "Europe/London"). Defaults to the application timezone.',
            ],
        ];
    }

    public function execute(array $parameters): ToolResult
    {
        try {
            $timezone = $parameters['timezone'] ?? config('app.timezone');
            $now = Date::now($timezone);

            return ToolResult::success([
                'datetime' => $now->toDateTimeString(),
                'date' => $now->toDateString(),
                'time' => $now->toTimeString(),
                'timezone' => $now->timezoneName,
                'day_of_week' => $now->dayName,
                'unix_timestamp' => $now->timestamp,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }
}
