<?php

use App\Models\Connection;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('edit page shows connections', function () {
    Connection::factory()->create(['user_id' => $this->user->id, 'name' => 'Work Gmail']);

    $this->get('/system/connections')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('connections/Index')
            ->has('connections', 1)
            ->where('connections.0.name', 'Work Gmail')
        );
});

test('store creates a new connection', function () {
    $this->post('/system/connections', [
        'provider' => 'gmail',
        'name' => 'My Gmail',
        'email' => 'test@gmail.com',
        'password' => 'abcd-efgh-ijkl-mnop',
    ])->assertRedirect();

    $this->assertDatabaseHas('connections', [
        'user_id' => $this->user->id,
        'type' => 'mail',
        'provider' => 'gmail',
        'name' => 'My Gmail',
    ]);

    $connection = Connection::first();
    expect($connection->credentials['email'])->toBe('test@gmail.com');
});

test('store validates required fields', function () {
    $this->post('/system/connections', [])
        ->assertSessionHasErrors(['provider', 'name', 'email', 'password']);
});

test('store validates provider is allowed', function () {
    $this->post('/system/connections', [
        'provider' => 'yahoo',
        'name' => 'My Yahoo',
        'email' => 'test@yahoo.com',
        'password' => 'pass',
    ])->assertSessionHasErrors(['provider']);
});

test('update modifies connection name', function () {
    $connection = Connection::factory()->create(['user_id' => $this->user->id]);

    $this->patch("/system/connections/{$connection->id}", [
        'name' => 'Updated Name',
    ])->assertRedirect();

    expect($connection->fresh()->name)->toBe('Updated Name');
});

test('update toggles is_active', function () {
    $connection = Connection::factory()->create(['user_id' => $this->user->id, 'is_active' => true]);

    $this->patch("/system/connections/{$connection->id}", [
        'is_active' => false,
    ])->assertRedirect();

    expect($connection->fresh()->is_active)->toBeFalse();
});

test('update changes credentials', function () {
    $connection = Connection::factory()->create(['user_id' => $this->user->id]);

    $this->patch("/system/connections/{$connection->id}", [
        'email' => 'new@gmail.com',
        'password' => 'new-password',
    ])->assertRedirect();

    $fresh = $connection->fresh();
    expect($fresh->credentials['email'])->toBe('new@gmail.com');
    expect($fresh->credentials['password'])->toBe('new-password');
});

test('update prevents modifying another user connection', function () {
    $otherUser = User::factory()->create();
    $connection = Connection::factory()->create(['user_id' => $otherUser->id]);

    $this->patch("/system/connections/{$connection->id}", [
        'name' => 'Hacked',
    ])->assertForbidden();
});

test('destroy deletes a connection', function () {
    $connection = Connection::factory()->create(['user_id' => $this->user->id]);

    $this->delete("/system/connections/{$connection->id}")->assertRedirect();

    $this->assertDatabaseMissing('connections', ['id' => $connection->id]);
});

test('destroy prevents deleting another user connection', function () {
    $otherUser = User::factory()->create();
    $connection = Connection::factory()->create(['user_id' => $otherUser->id]);

    $this->delete("/system/connections/{$connection->id}")->assertForbidden();
});

test('edit page requires authentication', function () {
    auth()->logout();

    $this->get('/system/connections')->assertRedirect('/login');
});
