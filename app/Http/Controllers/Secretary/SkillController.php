<?php

namespace App\Http\Controllers\Secretary;

use App\Http\Controllers\Controller;
use App\Http\Requests\Secretary\RenameSkillRequest;
use App\Http\Requests\Secretary\StoreSkillRequest;
use App\Models\Skill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SkillController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('secretary/Skills', [
            'skills' => Skill::orderByDesc('updated_at')->get([
                'id', 'name', 'description', 'detailed_instructions', 'trigger_keywords', 'steps', 'memory_keys', 'updated_at',
            ]),
        ]);
    }

    public function store(StoreSkillRequest $request): RedirectResponse
    {
        Skill::create($request->validated());

        return to_route('secretary.skills.index');
    }

    public function update(StoreSkillRequest $request, Skill $skill): RedirectResponse
    {
        $skill->update($request->validated());

        return to_route('secretary.skills.index');
    }

    public function rename(RenameSkillRequest $request, Skill $skill): RedirectResponse
    {
        $skill->update(['name' => $request->validated('name')]);

        return to_route('secretary.skills.index');
    }

    public function refine(Request $request, Skill $skill): RedirectResponse
    {
        $conversation = $request->user()->conversations()->create([
            'channel' => 'web',
            'title' => "Refining: {$skill->name}",
            'skill_id' => $skill->id,
        ]);

        $summary = "**Skill:** {$skill->name}";

        if ($skill->description) {
            $summary .= "\n**Description:** {$skill->description}";
        }

        if ($skill->trigger_keywords) {
            $summary .= "\n**Triggers:** ".implode(', ', $skill->trigger_keywords);
        }

        if ($skill->steps) {
            $summary .= "\n**Steps:**";
            foreach ($skill->steps as $i => $step) {
                $summary .= "\n".($i + 1).". {$step['description']}";
            }
        }

        if ($skill->detailed_instructions) {
            $summary .= "\n\n**Detailed instructions:**\n".$skill->detailed_instructions;
        }

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => "I've loaded the **{$skill->name}** skill. Here's what I currently know:\n\n{$summary}\n\nWhat would you like to change? You can ask me to update the description, steps, trigger keywords, or anything else. When you're happy, just say \"save the skill\" and I'll update it.",
        ]);

        return to_route('secretary.chat.show', $conversation);
    }

    public function destroy(Skill $skill): RedirectResponse
    {
        $skill->delete();

        return to_route('secretary.skills.index');
    }
}
