<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Connection;
use App\Services\Connections\ConnectionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConnectionController extends Controller
{
    public function edit(Request $request): Response
    {
        $connections = $request->user()->connections()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Connection $c) => [
                'id' => $c->id,
                'type' => $c->type,
                'provider' => $c->provider,
                'name' => $c->name,
                'email' => $c->credentials['email'] ?? '',
                'is_active' => $c->is_active,
                'created_at' => $c->created_at->diffForHumans(),
            ]);

        return Inertia::render('system/Connections', [
            'connections' => $connections,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:gmail'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->connections()->create([
            'type' => 'mail',
            'provider' => $validated['provider'],
            'name' => $validated['name'],
            'credentials' => [
                'email' => $validated['email'],
                'password' => $validated['password'],
            ],
        ]);

        return back()->with('success', 'Connection added.');
    }

    public function update(Request $request, Connection $connection): RedirectResponse
    {
        if ($connection->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['name'])) {
            $connection->name = $validated['name'];
        }

        if (isset($validated['email']) || filled($validated['password'] ?? null)) {
            $credentials = $connection->credentials;

            if (isset($validated['email'])) {
                $credentials['email'] = $validated['email'];
            }

            if (filled($validated['password'] ?? null)) {
                $credentials['password'] = $validated['password'];
            }

            $connection->credentials = $credentials;
        }

        if (isset($validated['is_active'])) {
            $connection->is_active = $validated['is_active'];
        }

        $connection->save();

        return back()->with('success', 'Connection updated.');
    }

    public function destroy(Request $request, Connection $connection): RedirectResponse
    {
        if ($connection->user_id !== $request->user()->id) {
            abort(403);
        }

        $connection->delete();

        return back()->with('success', 'Connection removed.');
    }

    public function test(Request $request, Connection $connection, ConnectionManager $manager): JsonResponse
    {
        if ($connection->user_id !== $request->user()->id) {
            abort(403);
        }

        try {
            $connector = $manager->resolveMailConnector($connection);
            $connector->list(1);

            return response()->json(['success' => true, 'message' => 'Connection successful.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Connection failed: '.$e->getMessage()], 422);
        }
    }
}
