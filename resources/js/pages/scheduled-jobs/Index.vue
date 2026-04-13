<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { CalendarClock, ChevronDown, Copy, Pencil, Play, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ScheduledJobController from '@/actions/App/Http/Controllers/Settings/ScheduledJobController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { index } from '@/routes/scheduled-jobs';

type ScheduledJob = {
    id: number;
    title: string;
    prompt: string;
    source: string;
    frequency: string;
    respond_channel: string;
    scheduled_at: string;
    last_run_at: string | null;
    is_active: boolean;
    created_at: string;
};

type Props = {
    jobs: ScheduledJob[];
    archivedJobs: ScheduledJob[];
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Scheduled Jobs',
                href: index(),
            },
        ],
    },
});

const page = usePage();
const success = computed(() => page.props.flash?.success as string | undefined);

const showAddDialog = ref(false);
const showEditDialog = ref(false);
const editingJob = ref<ScheduledJob | null>(null);
const duplicatingJob = ref<ScheduledJob | null>(null);
const archiveOpen = ref(false);

const manualJobs = computed(() => props.jobs.filter(j => j.source === 'manual'));
const agentJobs = computed(() => props.jobs.filter(j => j.source === 'agent'));
const manualArchived = computed(() => props.archivedJobs.filter(j => j.source === 'manual'));
const agentArchived = computed(() => props.archivedJobs.filter(j => j.source === 'agent'));

const frequencyLabels: Record<string, string> = {
    once: 'One-time',
    hourly: 'Hourly',
    daily: 'Daily',
    weekly: 'Weekly',
    monthly: 'Monthly',
};

const channelLabels: Record<string, string> = {
    web: 'Web',
    telegram: 'Telegram',
};

const formatDate = (iso: string) => {
    return new Date(iso).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
};

const formatDatetimeLocal = (iso: string) => {
    const d = new Date(iso);
    const pad = (n: number) => n.toString().padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

const toggleActive = (job: ScheduledJob) => {
    router.patch(
        ScheduledJobController.update.url(job.id),
        { is_active: !job.is_active },
        { preserveScroll: true },
    );
};

const openEdit = (job: ScheduledJob) => {
    editingJob.value = { ...job };
    showEditDialog.value = true;
};

const duplicateJob = (job: ScheduledJob) => {
    duplicatingJob.value = { ...job };
    showAddDialog.value = true;
};

const triggerJob = (job: ScheduledJob) => {
    router.post(ScheduledJobController.trigger.url(job.id), {}, {
        preserveScroll: true,
    });
};

const deleteJob = (job: ScheduledJob) => {
    router.delete(ScheduledJobController.destroy.url(job.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Scheduled Jobs" />

    <div class="mx-auto w-full max-w-4xl px-4 py-6">
        <h1 class="sr-only">Scheduled Jobs</h1>

        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <Heading
                    variant="small"
                    title="Scheduled Jobs"
                    description="Manage one-time and recurring tasks for your secretary"
                />

                <Dialog v-model:open="showAddDialog">
                    <DialogTrigger as-child>
                        <Button size="sm">
                            <Plus class="mr-2 h-4 w-4" />
                            Add job
                        </Button>
                    </DialogTrigger>

                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Create scheduled job</DialogTitle>
                            <DialogDescription>
                                Schedule a one-time or recurring task. The secretary will execute the prompt at the
                                scheduled time.
                            </DialogDescription>
                        </DialogHeader>

                        <Form
                            v-bind="ScheduledJobController.store.form()"
                            class="space-y-4"
                            v-slot="{ errors, processing }"
                            @success="showAddDialog = false; duplicatingJob = null"
                        >
                            <div class="grid gap-2">
                                <Label for="title">Title</Label>
                                <Input id="title" name="title" placeholder="Morning coffee reminder" :default-value="duplicatingJob?.title ?? ''" />
                                <InputError :message="errors.title" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="prompt">Prompt</Label>
                                <Textarea
                                    id="prompt"
                                    name="prompt"
                                    placeholder="Remind me to get coffee"
                                    rows="3"
                                    :default-value="duplicatingJob?.prompt ?? ''"
                                />
                                <p class="text-sm text-muted-foreground">
                                    The message the secretary will process when this job fires.
                                </p>
                                <InputError :message="errors.prompt" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="frequency">Frequency</Label>
                                <Select name="frequency" :default-value="duplicatingJob?.frequency ?? 'once'">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select frequency" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="once">One-time</SelectItem>
                                        <SelectItem value="hourly">Hourly</SelectItem>
                                        <SelectItem value="daily">Daily</SelectItem>
                                        <SelectItem value="weekly">Weekly</SelectItem>
                                        <SelectItem value="monthly">Monthly</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="errors.frequency" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="respond_channel">Respond via</Label>
                                <Select name="respond_channel" :default-value="duplicatingJob?.respond_channel ?? 'web'">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select channel" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="web">Web</SelectItem>
                                        <SelectItem value="telegram">Telegram</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="errors.respond_channel" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="scheduled_at">Scheduled at</Label>
                                <Input id="scheduled_at" name="scheduled_at" type="datetime-local" />
                                <InputError :message="errors.scheduled_at" />
                            </div>

                            <DialogFooter>
                                <Button type="button" variant="outline" @click="showAddDialog = false">Cancel</Button>
                                <Button type="submit" :disabled="processing">
                                    {{ processing ? 'Creating...' : 'Create job' }}
                                </Button>
                            </DialogFooter>
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>

            <!-- Edit Dialog -->
            <Dialog v-model:open="showEditDialog">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit scheduled job</DialogTitle>
                        <DialogDescription>
                            Update the details for this scheduled job.
                        </DialogDescription>
                    </DialogHeader>

                    <Form
                        v-if="editingJob"
                        v-bind="ScheduledJobController.update.form(editingJob.id)"
                        class="space-y-4"
                        v-slot="{ errors, processing }"
                        @success="showEditDialog = false"
                    >
                        <div class="grid gap-2">
                            <Label for="edit-title">Title</Label>
                            <Input id="edit-title" name="title" :default-value="editingJob.title" />
                            <InputError :message="errors.title" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="edit-prompt">Prompt</Label>
                            <Textarea
                                id="edit-prompt"
                                name="prompt"
                                :default-value="editingJob.prompt"
                                rows="3"
                            />
                            <InputError :message="errors.prompt" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="edit-frequency">Frequency</Label>
                            <Select name="frequency" :default-value="editingJob.frequency">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select frequency" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="once">One-time</SelectItem>
                                    <SelectItem value="hourly">Hourly</SelectItem>
                                    <SelectItem value="daily">Daily</SelectItem>
                                    <SelectItem value="weekly">Weekly</SelectItem>
                                    <SelectItem value="monthly">Monthly</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.frequency" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="edit-respond_channel">Respond via</Label>
                            <Select name="respond_channel" :default-value="editingJob.respond_channel">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select channel" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="web">Web</SelectItem>
                                    <SelectItem value="telegram">Telegram</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.respond_channel" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="edit-scheduled_at">Scheduled at</Label>
                            <Input
                                id="edit-scheduled_at"
                                name="scheduled_at"
                                type="datetime-local"
                                :default-value="formatDatetimeLocal(editingJob.scheduled_at)"
                            />
                            <InputError :message="errors.scheduled_at" />
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" @click="showEditDialog = false">Cancel</Button>
                            <Button type="submit" :disabled="processing">
                                {{ processing ? 'Saving...' : 'Save changes' }}
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>

            <Alert v-if="success" class="mb-4 border-green-200 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200">
                <AlertTitle>Success</AlertTitle>
                <AlertDescription>{{ success }}</AlertDescription>
            </Alert>

            <Tabs default-value="manual">
                <TabsList>
                    <TabsTrigger value="manual">
                        My Jobs
                        <Badge v-if="manualJobs.length" variant="secondary" class="ml-2">{{ manualJobs.length }}</Badge>
                    </TabsTrigger>
                    <TabsTrigger value="agent">
                        Agent Jobs
                        <Badge v-if="agentJobs.length" variant="secondary" class="ml-2">{{ agentJobs.length }}</Badge>
                    </TabsTrigger>
                </TabsList>

                <TabsContent value="manual" class="space-y-4">
                    <div v-if="manualJobs.length === 0 && manualArchived.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                        <CalendarClock class="mx-auto mb-3 h-8 w-8 text-muted-foreground" />
                        <p class="text-sm text-muted-foreground">No scheduled jobs yet. Create one to get started.</p>
                    </div>

                    <template v-for="job in manualJobs" :key="job.id">
                        <Card>
                            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                                <div>
                                    <CardTitle class="text-base">{{ job.title }}</CardTitle>
                                    <CardDescription class="mt-1 line-clamp-2">{{ job.prompt }}</CardDescription>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Badge :variant="job.is_active ? 'default' : 'secondary'">
                                        {{ job.is_active ? 'Active' : 'Paused' }}
                                    </Badge>
                                    <Badge variant="outline">{{ frequencyLabels[job.frequency] ?? job.frequency }}</Badge>
                                    <Badge variant="outline">{{ channelLabels[job.respond_channel] ?? job.respond_channel }}</Badge>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div class="flex items-center justify-between">
                                    <div class="space-y-1 text-sm text-muted-foreground">
                                        <p>Scheduled: {{ formatDate(job.scheduled_at) }}</p>
                                        <p v-if="job.last_run_at">Last run: {{ formatDate(job.last_run_at) }}</p>
                                        <p>Created {{ job.created_at }}</p>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center gap-2">
                                            <Label :for="`active-${job.id}`" class="text-sm">Active</Label>
                                            <Switch
                                                :id="`active-${job.id}`"
                                                :model-value="job.is_active"
                                                @update:model-value="toggleActive(job)"
                                            />
                                        </div>
                                        <Button size="icon" variant="ghost" title="Trigger now" @click="triggerJob(job)">
                                            <Play class="h-4 w-4" />
                                        </Button>
                                        <Button size="icon" variant="ghost" @click="openEdit(job)">
                                            <Pencil class="h-4 w-4" />
                                        </Button>
                                        <Button size="icon" variant="ghost" @click="duplicateJob(job)">
                                            <Copy class="h-4 w-4" />
                                        </Button>
                                        <Button size="icon" variant="ghost" class="text-destructive" @click="deleteJob(job)">
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </template>

                    <Collapsible v-if="manualArchived.length > 0" v-model:open="archiveOpen">
                        <CollapsibleTrigger as-child>
                            <Button variant="ghost" class="w-full justify-between" size="sm">
                                <span class="text-sm text-muted-foreground">Archived ({{ manualArchived.length }})</span>
                                <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': archiveOpen }" />
                            </Button>
                        </CollapsibleTrigger>
                        <CollapsibleContent class="space-y-4 pt-2">
                            <Card v-for="job in manualArchived" :key="job.id" class="opacity-60">
                                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <div>
                                        <CardTitle class="text-base">{{ job.title }}</CardTitle>
                                        <CardDescription class="mt-1 line-clamp-2">{{ job.prompt }}</CardDescription>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <Badge variant="secondary">Completed</Badge>
                                        <Badge variant="outline">{{ channelLabels[job.respond_channel] ?? job.respond_channel }}</Badge>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div class="flex items-center justify-between">
                                        <div class="space-y-1 text-sm text-muted-foreground">
                                            <p v-if="job.last_run_at">Ran: {{ formatDate(job.last_run_at) }}</p>
                                            <p>Created {{ job.created_at }}</p>
                                        </div>
                                        <Button size="icon" variant="ghost" class="text-destructive" @click="deleteJob(job)">
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </CollapsibleContent>
                    </Collapsible>
                </TabsContent>

                <TabsContent value="agent" class="space-y-4">
                    <div v-if="agentJobs.length === 0 && agentArchived.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                        <CalendarClock class="mx-auto mb-3 h-8 w-8 text-muted-foreground" />
                        <p class="text-sm text-muted-foreground">No agent-created jobs yet. The secretary can create jobs during conversations.</p>
                    </div>

                    <template v-for="job in agentJobs" :key="job.id">
                        <Card>
                            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                                <div>
                                    <CardTitle class="text-base">{{ job.title }}</CardTitle>
                                    <CardDescription class="mt-1 line-clamp-2">{{ job.prompt }}</CardDescription>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Badge :variant="job.is_active ? 'default' : 'secondary'">
                                        {{ job.is_active ? 'Active' : 'Paused' }}
                                    </Badge>
                                    <Badge variant="outline">{{ frequencyLabels[job.frequency] ?? job.frequency }}</Badge>
                                    <Badge variant="outline">{{ channelLabels[job.respond_channel] ?? job.respond_channel }}</Badge>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div class="flex items-center justify-between">
                                    <div class="space-y-1 text-sm text-muted-foreground">
                                        <p>Scheduled: {{ formatDate(job.scheduled_at) }}</p>
                                        <p v-if="job.last_run_at">Last run: {{ formatDate(job.last_run_at) }}</p>
                                        <p>Created {{ job.created_at }}</p>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center gap-2">
                                            <Label :for="`active-${job.id}`" class="text-sm">Active</Label>
                                            <Switch
                                                :id="`active-${job.id}`"
                                                :model-value="job.is_active"
                                                @update:model-value="toggleActive(job)"
                                            />
                                        </div>
                                        <Button size="icon" variant="ghost" title="Trigger now" @click="triggerJob(job)">
                                            <Play class="h-4 w-4" />
                                        </Button>
                                        <Button size="icon" variant="ghost" @click="openEdit(job)">
                                            <Pencil class="h-4 w-4" />
                                        </Button>
                                        <Button size="icon" variant="ghost" @click="duplicateJob(job)">
                                            <Copy class="h-4 w-4" />
                                        </Button>
                                        <Button size="icon" variant="ghost" class="text-destructive" @click="deleteJob(job)">
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </template>

                    <Collapsible v-if="agentArchived.length > 0" v-model:open="archiveOpen">
                        <CollapsibleTrigger as-child>
                            <Button variant="ghost" class="w-full justify-between" size="sm">
                                <span class="text-sm text-muted-foreground">Archived ({{ agentArchived.length }})</span>
                                <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': archiveOpen }" />
                            </Button>
                        </CollapsibleTrigger>
                        <CollapsibleContent class="space-y-4 pt-2">
                            <Card v-for="job in agentArchived" :key="job.id" class="opacity-60">
                                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <div>
                                        <CardTitle class="text-base">{{ job.title }}</CardTitle>
                                        <CardDescription class="mt-1 line-clamp-2">{{ job.prompt }}</CardDescription>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <Badge variant="secondary">Completed</Badge>
                                        <Badge variant="outline">{{ channelLabels[job.respond_channel] ?? job.respond_channel }}</Badge>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div class="flex items-center justify-between">
                                        <div class="space-y-1 text-sm text-muted-foreground">
                                            <p v-if="job.last_run_at">Ran: {{ formatDate(job.last_run_at) }}</p>
                                            <p>Created {{ job.created_at }}</p>
                                        </div>
                                        <Button size="icon" variant="ghost" class="text-destructive" @click="deleteJob(job)">
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </CollapsibleContent>
                    </Collapsible>
                </TabsContent>
            </Tabs>
        </div>
    </div>
</template>
