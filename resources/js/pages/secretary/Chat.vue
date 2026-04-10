<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import DOMPurify from 'dompurify';
import { BookOpen, Brain, Circle, MessageSquarePlus, Square, Trash2 } from 'lucide-vue-next';
import { marked } from 'marked';
import { nextTick, onMounted, ref, watch } from 'vue';

marked.setOptions({ breaks: true });

function renderMarkdown(content: string | null): string {
    if (!content) return '';
    const html = marked.parse(content) as string;
    if (typeof window === 'undefined') return html;
    return DOMPurify.sanitize(html);
}
import ChatController from '@/actions/App/Http/Controllers/Secretary/ChatController';
import ConversationController from '@/actions/App/Http/Controllers/Secretary/ConversationController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { index as chatIndex } from '@/routes/secretary/chat';

type Conversation = {
    id: number;
    title: string;
    is_learn_mode: boolean;
    updated_at: string;
};

type Message = {
    id: number;
    role: 'user' | 'assistant' | 'tool' | 'system';
    content: string | null;
    tool_name: string | null;
    created_at: string;
};

type ActiveConversation = {
    id: number;
    title: string;
    is_learn_mode: boolean;
    is_processing: boolean;
    learn_mode_skill_name: string | null;
    ai_provider: string | null;
};

const props = defineProps<{
    conversations: Conversation[];
    activeConversation?: ActiveConversation;
    messages?: Message[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Secretary', href: chatIndex() }],
    },
});

const messages = ref<Message[]>(props.messages ?? []);
const inputText = ref('');
const isSending = ref(false);
const isRecording = ref(props.activeConversation?.is_learn_mode ?? false);
const isProcessing = ref(props.activeConversation?.is_processing ?? false);
const recordingSkillName = ref(props.activeConversation?.learn_mode_skill_name ?? '');

const lastMessageId = ref<number>(
    messages.value.length > 0 ? messages.value[messages.value.length - 1].id : 0,
);

let pollInterval: ReturnType<typeof setInterval> | null = null;

function applyNewMessages(newMsgs: Message[], convState: { is_processing: boolean; is_learn_mode: boolean; learn_mode_skill_name: string | null }) {
    if (newMsgs.length > 0) {
        messages.value.push(...newMsgs);
        lastMessageId.value = newMsgs[newMsgs.length - 1].id;
        scrollToBottom();
    }
    isProcessing.value = convState.is_processing;
    isRecording.value = convState.is_learn_mode;
    recordingSkillName.value = convState.learn_mode_skill_name ?? '';
}

function startPolling() {
    if (pollInterval || !props.activeConversation) return;
    pollInterval = setInterval(async () => {
        try {
            const url = ChatController.poll.url({ conversation: props.activeConversation!.id });
            const response = await fetch(`${url}?since=${lastMessageId.value}`, {
                headers: { Accept: 'application/json' },
            });
            if (response.ok) {
                const data = await response.json();
                applyNewMessages(data.messages, data.conversation);
                if (!data.conversation.is_processing) {
                    stopPolling();
                    isSending.value = false;
                }
            }
        } catch {
            // Network blip — keep polling
        }
    }, 1500);
}

function stopPolling() {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
}

// Inline record panel state
const showRecordPanel = ref(false);
const newSkillName = ref('');

const messagesContainer = ref<HTMLElement | null>(null);

onMounted(() => {
    scrollToBottom();
    if (props.activeConversation?.is_processing) {
        isSending.value = true;
        startPolling();
    }
});

// When Inertia swaps to a different conversation (same component, new props), reset all state.
watch(
    () => props.activeConversation?.id,
    () => {
        stopPolling();
        messages.value = props.messages ?? [];
        lastMessageId.value = messages.value.length > 0 ? messages.value[messages.value.length - 1].id : 0;
        inputText.value = '';
        isSending.value = false;
        isRecording.value = props.activeConversation?.is_learn_mode ?? false;
        isProcessing.value = props.activeConversation?.is_processing ?? false;
        recordingSkillName.value = props.activeConversation?.learn_mode_skill_name ?? '';
        showRecordPanel.value = false;
        newSkillName.value = '';
        scrollToBottom();
        if (props.activeConversation?.is_processing) {
            isSending.value = true;
            startPolling();
        }
    },
);

async function scrollToBottom() {
    await nextTick();
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    }
}

function csrf(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

async function sendMessage() {
    const text = inputText.value.trim();
    if (!text || isSending.value || !props.activeConversation) return;

    isSending.value = true;
    inputText.value = '';

    try {
        const url = ChatController.send.url({ conversation: props.activeConversation.id });
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            body: JSON.stringify({ message: text }),
        });

        if (response.ok) {
            const data = await response.json();
            // send() returns all messages from DB (since=0), so replace the full list.
            messages.value = data.messages;
            lastMessageId.value = data.messages.length > 0 ? data.messages[data.messages.length - 1].id : lastMessageId.value;
            isProcessing.value = data.conversation.is_processing;
            isRecording.value = data.conversation.is_learn_mode;
            recordingSkillName.value = data.conversation.learn_mode_skill_name ?? '';
            await scrollToBottom();
            if (data.conversation.is_processing) {
                startPolling();
            } else {
                isSending.value = false;
            }
        } else {
            isSending.value = false;
        }
    } catch (e) {
        console.error('Send failed:', e);
        isSending.value = false;
    }
}

async function newConversation() {
    router.post(ChatController.store.url());
}

function deleteConversation(conv: Conversation, e: MouseEvent) {
    e.preventDefault();
    if (!confirm(`Delete "${conv.title}"?`)) return;
    router.delete(ConversationController.destroy.url({ conversation: conv.id }));
}

async function startRecording() {
    if (!props.activeConversation || !newSkillName.value.trim()) return;

    isSending.value = true;
    showRecordPanel.value = false;

    const url = ChatController.startLearnMode.url({ conversation: props.activeConversation.id });
    const response = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        body: JSON.stringify({ skill_name: newSkillName.value }),
    });

    if (response.ok) {
        const data = await response.json();
        messages.value = data.messages;
        isRecording.value = true;
        recordingSkillName.value = newSkillName.value;
        newSkillName.value = '';
        await scrollToBottom();
    }

    isSending.value = false;
}

async function stopRecording() {
    if (!props.activeConversation) return;

    isSending.value = true;
    const url = ChatController.endLearnMode.url({ conversation: props.activeConversation.id });
    const response = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
    });

    if (response.ok) {
        const data = await response.json();
        messages.value = data.messages;
        isRecording.value = false;
        recordingSkillName.value = '';
        await scrollToBottom();
    }

    isSending.value = false;
}

function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
}

function handleSkillNameKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter') startRecording();
    if (e.key === 'Escape') { showRecordPanel.value = false; newSkillName.value = ''; }
}

function formatRole(msg: Message): string {
    if (msg.role === 'tool') return `Tool: ${msg.tool_name ?? 'unknown'}`;
    return msg.role === 'user' ? 'You' : 'Secretary';
}

function parseToolContent(content: string | null): Record<string, unknown> | null {
    if (!content) return null;
    try {
        return JSON.parse(content);
    } catch {
        return null;
    }
}

function toolScreenshot(msg: Message): string | null {
    const parsed = parseToolContent(msg.content);
    return typeof parsed?.screenshot_base64 === 'string' ? parsed.screenshot_base64 : null;
}

function toolDownloadUrl(msg: Message): string | null {
    const parsed = parseToolContent(msg.content);
    return typeof parsed?.download_url === 'string' ? parsed.download_url : null;
}

function toolDownloadFilename(msg: Message): string {
    const parsed = parseToolContent(msg.content);
    return typeof parsed?.download_filename === 'string' ? parsed.download_filename : 'Download';
}

function toolText(msg: Message): string {
    const parsed = parseToolContent(msg.content);
    if (!parsed) return msg.content ?? '';
    const { screenshot_base64, format, download_url, download_filename, size_bytes, ...rest } = parsed;
    const remaining = Object.keys(rest).length ? JSON.stringify(rest, null, 2) : null;
    return remaining ?? '';
}
</script>

<template>
    <Head title="Secretary" />

    <div class="flex flex-1 min-h-0 flex-row overflow-hidden">
        <!-- Conversation sidebar -->
        <aside class="flex w-64 flex-shrink-0 flex-col gap-2 overflow-y-auto border-r border-sidebar-border/70 p-3 dark:border-sidebar-border">
            <Button variant="outline" class="w-full justify-start gap-2" @click="newConversation">
                <MessageSquarePlus class="size-4" />
                New conversation
            </Button>

            <nav class="mt-1 flex flex-col gap-1">
                <div
                    v-for="conv in conversations"
                    :key="conv.id"
                    class="group flex items-center gap-1 rounded-lg transition-colors hover:bg-sidebar-accent/50"
                    :class="{ 'bg-sidebar-accent font-medium': activeConversation?.id === conv.id }"
                >
                    <Link
                        :href="ChatController.show.url({ conversation: conv.id })"
                        class="flex min-w-0 flex-1 items-center gap-2 px-3 py-2 text-sm"
                    >
                        <span
                            v-if="conv.is_learn_mode"
                            class="size-2 shrink-0 animate-pulse rounded-full bg-red-500"
                        />
                        <span class="min-w-0 flex-1 truncate">{{ conv.title }}</span>
                    </Link>
                    <button
                        class="mr-1.5 shrink-0 rounded p-1 opacity-0 transition-opacity hover:bg-destructive/10 hover:text-destructive group-hover:opacity-100"
                        title="Delete conversation"
                        @click="deleteConversation(conv, $event)"
                    >
                        <Trash2 class="size-3" />
                    </button>
                </div>
            </nav>

            <div class="mt-auto flex flex-col gap-1 border-t border-sidebar-border/50 pt-2">
                <Link
                    href="/secretary/skills"
                    class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-sidebar-accent/50"
                >
                    <BookOpen class="size-4" />
                    Skills library
                </Link>
                <Link
                    href="/secretary/memories"
                    class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-sidebar-accent/50"
                >
                    <Brain class="size-4" />
                    Memory vault
                </Link>
            </div>
        </aside>

        <!-- Chat main area -->
        <div class="flex flex-1 flex-col overflow-hidden">
            <!-- Recording indicator banner -->
            <div
                v-if="isRecording"
                class="flex items-center justify-between border-b border-red-200/60 bg-red-50/80 px-4 py-2 dark:border-red-900/40 dark:bg-red-950/20"
            >
                <div class="flex items-center gap-2">
                    <span class="size-2 animate-pulse rounded-full bg-red-500" />
                    <span class="text-sm text-red-700 dark:text-red-400">Recording<span v-if="recordingSkillName">: <em>{{ recordingSkillName }}</em></span></span>
                </div>
                <Button size="sm" variant="ghost" class="h-7 text-red-600 hover:bg-red-100 dark:hover:bg-red-950" :disabled="isSending" @click="stopRecording">
                    <Square class="mr-1.5 size-3 fill-current" /> Stop
                </Button>
            </div>

            <!-- No conversation selected -->
            <div v-if="!activeConversation" class="flex flex-1 flex-col items-center justify-center gap-4 text-muted-foreground">
                <Brain class="size-12 opacity-20" />
                <p class="text-sm">Select a conversation or start a new one.</p>
                <Button variant="outline" @click="newConversation">
                    <MessageSquarePlus class="mr-2 size-4" />
                    New conversation
                </Button>
            </div>

            <!-- Message list -->
            <div v-else ref="messagesContainer" class="flex-1 overflow-y-auto p-4">
                <div v-if="messages.length === 0" class="flex h-full flex-col items-center justify-center gap-2 text-muted-foreground">
                    <Brain class="size-8 opacity-20" />
                    <p class="text-sm">How can I help you today?</p>
                </div>

                <template v-else>
                    <div
                        v-for="msg in messages.filter((m) => m.role !== 'system')"
                        :key="msg.id"
                        class="mb-4"
                    >
                        <div v-if="msg.role === 'tool'" class="flex flex-col gap-1.5">
                            <Badge variant="outline" class="shrink-0 self-start text-xs">{{ msg.tool_name }}</Badge>
                            <img
                                v-if="toolScreenshot(msg)"
                                :src="`data:image/jpeg;base64,${toolScreenshot(msg)}`"
                                class="max-w-md rounded border border-border"
                                alt="Browser screenshot"
                            />
                            <a
                                v-if="toolDownloadUrl(msg)"
                                :href="toolDownloadUrl(msg)!"
                                :download="toolDownloadFilename(msg)"
                                target="_blank"
                                class="inline-flex items-center gap-1.5 rounded border border-border bg-muted/40 px-3 py-1.5 text-xs hover:bg-muted"
                            >📄 {{ toolDownloadFilename(msg) }}</a>
                            <pre v-if="toolText(msg)" class="max-h-32 overflow-auto rounded bg-muted/40 px-2 py-1 text-xs">{{ toolText(msg) }}</pre>
                        </div>

                        <div v-else class="flex items-start gap-3" :class="{ 'flex-row-reverse': msg.role === 'user' }">
                            <div
                                class="max-w-[80%] rounded-2xl px-4 py-2 text-sm"
                                :class="msg.role === 'user' ? 'bg-primary text-primary-foreground' : 'bg-muted/60 text-foreground'"
                            >
                                <p class="mb-1 text-xs font-medium opacity-60">{{ formatRole(msg) }}</p>
                                <p v-if="msg.role === 'user'" class="whitespace-pre-wrap break-words">{{ msg.content }}</p>
                                <div v-else class="prose prose-sm max-w-none dark:prose-invert" v-html="renderMarkdown(msg.content)" />
                            </div>
                        </div>
                    </div>

                    <div v-if="isProcessing || isSending" class="mb-4 flex items-start gap-3">
                        <div class="rounded-2xl bg-muted/60 px-4 py-3">
                            <div class="flex gap-1">
                                <span class="size-1.5 animate-bounce rounded-full bg-muted-foreground/50 [animation-delay:0ms]"></span>
                                <span class="size-1.5 animate-bounce rounded-full bg-muted-foreground/50 [animation-delay:150ms]"></span>
                                <span class="size-1.5 animate-bounce rounded-full bg-muted-foreground/50 [animation-delay:300ms]"></span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Input bar -->
            <div v-if="activeConversation" class="border-t border-sidebar-border/70 p-3 dark:border-sidebar-border">
                <!-- Inline "what should I record?" panel -->
                <div v-if="showRecordPanel" class="mb-2 flex items-center gap-2 rounded-lg border border-red-200/60 bg-red-50/60 px-3 py-2 dark:border-red-900/40 dark:bg-red-950/20">
                    <span class="size-2 shrink-0 rounded-full bg-red-500" />
                    <Input
                        v-model="newSkillName"
                        placeholder="What should I learn? (e.g. Generate Toggl report)"
                        class="h-7 flex-1 border-0 bg-transparent p-0 text-sm shadow-none focus-visible:ring-0"
                        autofocus
                        @keydown="handleSkillNameKeydown"
                    />
                    <Button size="sm" class="h-7" :disabled="!newSkillName.trim()" @click="startRecording">Start</Button>
                    <Button size="sm" variant="ghost" class="h-7" @click="showRecordPanel = false; newSkillName = ''">✕</Button>
                </div>

                <div class="flex items-end gap-2">
                    <Textarea
                        v-model="inputText"
                        placeholder="Type a message…"
                        rows="2"
                        class="flex-1 resize-none"
                        :disabled="isSending || isProcessing"
                        @keydown="handleKeydown"
                    />
                    <div class="flex flex-col gap-1">
                        <Button :disabled="isSending || isProcessing || !inputText.trim()" @click="sendMessage">Send</Button>
                        <Button
                            v-if="!isRecording"
                            size="sm"
                            variant="outline"
                            class="h-7 text-muted-foreground hover:text-red-500"
                            :disabled="isSending"
                            :title="'Teach me a new skill'"
                            @click="showRecordPanel = !showRecordPanel"
                        >
                            <Circle class="size-3 fill-current" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
