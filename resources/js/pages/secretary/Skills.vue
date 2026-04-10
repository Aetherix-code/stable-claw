<script setup lang="ts">
import { router, Form, Head, Link } from '@inertiajs/vue3';
import { Brain, ChevronDown, MessageSquareText, Pencil, Trash2 } from 'lucide-vue-next';
import { nextTick, ref } from 'vue';
import SkillController from '@/actions/App/Http/Controllers/Secretary/SkillController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { index } from '@/routes/secretary/skills';
import { index as chatIndex } from '@/routes/secretary/chat';

type Skill = {
    id: number;
    name: string;
    description: string | null;
    detailed_instructions: string | null;
    trigger_keywords: string[] | null;
    steps: { description: string; tool: string; action: string; notes?: string }[] | null;
    memory_keys: string[] | null;
    updated_at: string;
};

defineProps<{ skills: Skill[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Skills library', href: index() }],
    },
});

const editingId = ref<number | null>(null);
const editingName = ref('');
const nameInputRef = ref<HTMLInputElement | null>(null);

function startRename(skill: Skill) {
    editingId.value = skill.id;
    editingName.value = skill.name;
    nextTick(() => nameInputRef.value?.select());
}

function cancelRename() {
    editingId.value = null;
    editingName.value = '';
}

function submitRename(skill: Skill) {
    const trimmed = editingName.value.trim();
    if (!trimmed || trimmed === skill.name) {
        cancelRename();
        return;
    }
    router.patch(SkillController.rename.url({ skill: skill.id }), { name: trimmed }, {
        onFinish: () => cancelRename(),
    });
}

const expandedInstructions = ref<Set<number>>(new Set());

function toggleInstructions(skillId: number) {
    if (expandedInstructions.value.has(skillId)) {
        expandedInstructions.value.delete(skillId);
    } else {
        expandedInstructions.value.add(skillId);
    }
    expandedInstructions.value = new Set(expandedInstructions.value);
}
</script>

<template>
    <Head title="Skills library" />

    <div class="flex flex-col gap-6 p-6">
        <div class="flex items-center justify-between">
            <Heading title="Skills library" description="Skills the secretary has learned. Teach new ones by chatting." />
            <Link :href="chatIndex()">
                <Button variant="outline">
                    <Brain class="mr-2 size-4" />
                    Go to chat
                </Button>
            </Link>
        </div>

        <div v-if="skills.length === 0" class="flex flex-col items-center gap-3 py-16 text-muted-foreground">
            <Brain class="size-10 opacity-20" />
            <p class="text-sm">No skills yet. Ask the secretary to learn something new in the chat.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Card v-for="skill in skills" :key="skill.id">
                <CardHeader class="pb-2">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <input
                                v-if="editingId === skill.id"
                                ref="nameInputRef"
                                v-model="editingName"
                                class="w-full rounded border border-border bg-background px-1 py-0.5 text-base font-semibold leading-tight text-foreground outline-none ring-1 ring-primary focus:ring-2"
                                @blur="submitRename(skill)"
                                @keyup.enter="submitRename(skill)"
                                @keyup.escape="cancelRename()"
                                @click.stop
                            />
                            <CardTitle v-else class="cursor-pointer truncate text-base hover:text-primary" @click="startRename(skill)">
                                {{ skill.name }}
                            </CardTitle>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            <button
                                type="button"
                                class="text-muted-foreground transition-colors hover:text-foreground"
                                title="Rename skill"
                                @click="startRename(skill)"
                            >
                                <Pencil class="size-3.5" />
                            </button>
                            <Form v-bind="SkillController.destroy.form({ skill: skill.id })">
                                <button type="submit" class="text-muted-foreground transition-colors hover:text-destructive" title="Delete skill">
                                    <Trash2 class="size-4" />
                                </button>
                            </Form>
                        </div>
                    </div>
                    <CardDescription v-if="skill.description">{{ skill.description }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="skill.trigger_keywords?.length" class="mb-3 flex flex-wrap gap-1">
                        <Badge v-for="kw in skill.trigger_keywords" :key="kw" variant="secondary" class="text-xs">
                            {{ kw }}
                        </Badge>
                    </div>
                    <div v-if="skill.steps?.length" class="space-y-1">
                        <p class="text-xs font-medium text-muted-foreground">Steps ({{ skill.steps.length }})</p>
                        <ol class="list-inside list-decimal space-y-0.5">
                            <li v-for="(step, i) in skill.steps.slice(0, 4)" :key="i" class="truncate text-xs text-muted-foreground">
                                {{ step.description }}
                            </li>
                            <li v-if="skill.steps.length > 4" class="text-xs text-muted-foreground">
                                +{{ skill.steps.length - 4 }} more…
                            </li>
                        </ol>
                    </div>
                    <div v-if="skill.memory_keys?.length" class="mt-2 flex flex-wrap gap-1">
                        <Badge v-for="key in skill.memory_keys" :key="key" variant="outline" class="text-xs">🔑 {{ key }}</Badge>
                    </div>
                    <div v-if="skill.detailed_instructions" class="mt-3 border-t pt-3">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between text-xs font-medium text-muted-foreground hover:text-foreground"
                            @click="toggleInstructions(skill.id)"
                        >
                            <span>Detailed instructions</span>
                            <ChevronDown
                                class="size-3.5 transition-transform"
                                :class="{ 'rotate-180': expandedInstructions.has(skill.id) }"
                            />
                        </button>
                        <pre
                            v-if="expandedInstructions.has(skill.id)"
                            class="mt-2 max-h-64 overflow-auto whitespace-pre-wrap rounded bg-muted/40 px-2 py-1.5 text-xs text-foreground"
                        >{{ skill.detailed_instructions }}</pre>
                    </div>
                    <div class="mt-3 border-t pt-3">
                        <Form v-bind="SkillController.refine.form({ skill: skill.id })">
                            <button
                                type="submit"
                                class="flex items-center gap-1.5 text-xs text-muted-foreground transition-colors hover:text-foreground"
                            >
                                <MessageSquareText class="size-3.5" />
                                Refine in chat
                            </button>
                        </Form>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>

