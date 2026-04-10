<?php

namespace App\Http\Controllers\Secretary;

use App\Http\Controllers\Controller;
use App\Http\Requests\Secretary\StoreMemoryRequest;
use App\Models\Memory;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MemoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('secretary/Memories', [
            'memories' => Memory::orderBy('type')->orderBy('key')->get()->map(fn ($m) => [
                'id' => $m->id,
                'key' => $m->key,
                'display_value' => $m->display_value,
                'type' => $m->type,
                'is_sensitive' => $m->is_sensitive,
                'description' => $m->description,
                'updated_at' => $m->updated_at,
            ]),
        ]);
    }

    public function store(StoreMemoryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        Memory::remember(
            key: $data['key'],
            value: $data['value'],
            type: $data['type'],
            sensitive: (bool) ($data['is_sensitive'] ?? false),
            description: $data['description'] ?? null,
        );

        return to_route('secretary.memories.index');
    }

    public function destroy(Memory $memory): RedirectResponse
    {
        $memory->delete();

        return to_route('secretary.memories.index');
    }
}
