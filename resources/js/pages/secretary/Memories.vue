<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import MemoryController from '@/actions/App/Http/Controllers/Secretary/MemoryController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { index } from '@/routes/secretary/memories';

type Memory = {
    id: number;
    key: string;
    display_value: string;
    type: 'fact' | 'credential' | 'preference';
    is_sensitive: boolean;
    description: string | null;
    updated_at: string;
};

defineProps<{ memories: Memory[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Memory vault', href: index() }],
    },
});

const showForm = ref(false);
const isSensitive = ref(false);

const typeColors = {
    fact: 'secondary',
    credential: 'destructive',
    preference: 'outline',
} as const;
</script>

<template>
    <Head title="Memory vault" />

    <div class="flex flex-col gap-6 p-6">
        <div class="flex items-center justify-between">
            <Heading title="Memory vault" description="Persistent facts, credentials, and preferences the secretary can recall." />
            <Button @click="showForm = !showForm">
                <Plus class="mr-2 size-4" />
                Add memory
            </Button>
        </div>

        <!-- Add memory form -->
        <Form
            v-if="showForm"
            v-bind="MemoryController.store.form()"
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            v-slot="{ processing }"
        >
            <h3 class="mb-4 font-semibold">New memory</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="mem-key">Key</Label>
                    <Input id="mem-key" name="key" required placeholder="e.g. toggl_api_key" />
                </div>
                <div class="grid gap-2">
                    <Label for="mem-type">Type</Label>
                    <Select name="type" default-value="fact">
                        <SelectTrigger id="mem-type">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="fact">Fact</SelectItem>
                            <SelectItem value="credential">Credential</SelectItem>
                            <SelectItem value="preference">Preference</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="col-span-full grid gap-2">
                    <Label for="mem-value">Value</Label>
                    <Input id="mem-value" name="value" required :type="isSensitive ? 'password' : 'text'" placeholder="Value" />
                </div>
                <div class="col-span-full grid gap-2">
                    <Label for="mem-desc">Description (optional)</Label>
                    <Textarea id="mem-desc" name="description" rows="2" placeholder="What is this memory for?" />
                </div>
                <div class="col-span-full flex items-center gap-3">
                    <Switch
                        id="sensitive"
                        name="is_sensitive"
                        :checked="isSensitive"
                        @update:checked="(v: boolean) => (isSensitive = v)"
                    />
                    <Label for="sensitive" class="cursor-pointer">Sensitive (encrypt &amp; mask value)</Label>
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <Button type="submit" :disabled="processing">Save</Button>
            </div>
        </Form>

        <!-- Memory list -->
        <div v-if="memories.length === 0" class="py-12 text-center text-sm text-muted-foreground">
            No memories stored yet.
        </div>

        <div class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
            <table v-if="memories.length" class="w-full text-sm">
                <thead class="border-b bg-muted/30">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium">Key</th>
                        <th class="px-4 py-2 text-left font-medium">Value</th>
                        <th class="px-4 py-2 text-left font-medium">Type</th>
                        <th class="px-4 py-2 text-left font-medium">Description</th>
                        <th class="w-12 px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="mem in memories" :key="mem.id" class="border-b last:border-0 hover:bg-muted/20">
                        <td class="px-4 py-2 font-mono text-xs">{{ mem.key }}</td>
                        <td class="px-4 py-2 font-mono text-xs">{{ mem.display_value }}</td>
                        <td class="px-4 py-2">
                            <Badge :variant="typeColors[mem.type]" class="text-xs capitalize">{{ mem.type }}</Badge>
                        </td>
                        <td class="px-4 py-2 text-muted-foreground">{{ mem.description ?? '—' }}</td>
                        <td class="px-4 py-2">
                            <Form v-bind="MemoryController.destroy.form({ memory: mem.id })">
                                <button type="submit" class="text-muted-foreground transition-colors hover:text-destructive">
                                    <Trash2 class="size-4" />
                                </button>
                            </Form>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
