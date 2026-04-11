<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;

test('data settings page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('data.edit'));

    $response->assertOk();
});

test('import rejects non-sqlite files', function () {
    $user = User::factory()->create();

    $file = UploadedFile::fake()->createWithContent(
        'bad.txt',
        'not a database'
    );

    $response = $this
        ->actingAs($user)
        ->post(route('data.import'), ['file' => $file]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('file');
});

test('import rejects invalid sqlite files', function () {
    $user = User::factory()->create();

    $file = UploadedFile::fake()->createWithContent(
        'bad.sqlite',
        'this is not a real sqlite database'
    );

    $response = $this
        ->actingAs($user)
        ->post(route('data.import'), ['file' => $file]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('file');
});

test('import requires a file', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('data.import'));

    $response->assertRedirect();
    $response->assertSessionHasErrors('file');
});

test('guests cannot access data settings', function () {
    $this->get(route('data.edit'))->assertRedirect(route('login'));
    $this->get(route('data.export'))->assertRedirect(route('login'));
    $this->post(route('data.import'))->assertRedirect(route('login'));
});
