<?php

use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeSkill(array $attrs = []): Skill
{
    return Skill::create(array_merge([
        'name' => 'Test Skill',
        'description' => 'A test skill',
        'trigger_keywords' => ['test'],
        'steps' => [['description' => 'Do something', 'tool' => 'browser', 'action' => 'navigate']],
        'memory_keys' => [],
        'transcript' => '',
    ], $attrs));
}

test('authenticated user can rename a skill', function () {
    $user = User::factory()->create();
    $skill = makeSkill();

    $this->actingAs($user)
        ->patch(route('secretary.skills.rename', $skill), ['name' => 'Renamed Skill'])
        ->assertRedirect(route('secretary.skills.index'));

    expect($skill->fresh()->name)->toBe('Renamed Skill');
});

test('rename requires a name', function () {
    $user = User::factory()->create();
    $skill = makeSkill();

    $this->actingAs($user)
        ->patch(route('secretary.skills.rename', $skill), ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('unauthenticated user cannot rename a skill', function () {
    $skill = makeSkill();

    $this->patch(route('secretary.skills.rename', $skill), ['name' => 'New Name'])
        ->assertRedirect(route('login'));
});

test('authenticated user can start a refine conversation for a skill', function () {
    $user = User::factory()->create();
    $skill = makeSkill(['name' => 'Browser Automation']);

    $response = $this->actingAs($user)
        ->post(route('secretary.skills.refine', $skill));

    $response->assertRedirect();

    $conversation = $user->conversations()->where('skill_id', $skill->id)->first();
    expect($conversation)->not->toBeNull();
    expect($conversation->title)->toBe('Refining: Browser Automation');

    $firstMessage = $conversation->messages()->first();
    expect($firstMessage->role)->toBe('assistant');
    expect($firstMessage->content)->toContain('Browser Automation');
});

test('unauthenticated user cannot start a refine conversation', function () {
    $skill = makeSkill();

    $this->post(route('secretary.skills.refine', $skill))
        ->assertRedirect(route('login'));
});
