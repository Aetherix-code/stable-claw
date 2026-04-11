<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import TelegramSettingsController from '@/actions/App/Http/Controllers/Settings/TelegramSettingsController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/telegram/settings';

type Props = {
    settings: {
        bot_token_configured: boolean;
        allowed_usernames: string[];
        conversation_timeout_minutes: number;
    };
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'System settings',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const success = computed(() => page.props.flash?.success as string | undefined);
</script>

<template>
    <Head title="Telegram settings" />

    <h1 class="sr-only">Telegram settings</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Telegram bot"
            description="Configure the Telegram bot integration for your secretary"
        />

        <Alert v-if="success" class="mb-4">
            <AlertTitle>Success</AlertTitle>
            <AlertDescription>{{ success }}</AlertDescription>
        </Alert>

        <Form
            v-bind="TelegramSettingsController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing, recentlySuccessful }"
        >
            <div class="grid gap-2">
                <Label for="bot_token">Bot token</Label>
                <Input
                    id="bot_token"
                    name="bot_token"
                    type="password"
                    class="mt-1 block w-full"
                    :placeholder="props.settings.bot_token_configured ? '••••••••' : 'Enter your Telegram bot token'"
                    autocomplete="off"
                />
                <p class="text-sm text-muted-foreground">
                    {{ props.settings.bot_token_configured ? 'A bot token is configured. Enter a new value to replace it.' : 'Get a token from @BotFather on Telegram.' }}
                </p>
                <InputError :message="errors.bot_token" />
            </div>

            <div class="grid gap-2">
                <Label for="allowed_usernames">Allowed usernames</Label>
                <Input
                    id="allowed_usernames"
                    name="allowed_usernames"
                    class="mt-1 block w-full"
                    :default-value="props.settings.allowed_usernames.join(', ')"
                    placeholder="username1, username2"
                    autocomplete="off"
                />
                <p class="text-sm text-muted-foreground">
                    Comma-separated Telegram usernames (without @) allowed to use the bot. Leave empty to allow anyone.
                </p>
                <InputError :message="errors.allowed_usernames" />
            </div>

            <div class="grid gap-2">
                <Label for="conversation_timeout_minutes">Conversation timeout (minutes)</Label>
                <Input
                    id="conversation_timeout_minutes"
                    name="conversation_timeout_minutes"
                    type="number"
                    class="mt-1 block w-full max-w-32"
                    :default-value="String(props.settings.conversation_timeout_minutes)"
                    min="1"
                    max="10080"
                />
                <p class="text-sm text-muted-foreground">
                    After this many minutes of inactivity, a new conversation will be created for the next Telegram message.
                </p>
                <InputError :message="errors.conversation_timeout_minutes" />
            </div>

            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="processing">
                    {{ processing ? 'Saving...' : 'Save' }}
                </Button>

                <p
                    v-if="recentlySuccessful"
                    class="text-sm text-muted-foreground"
                >
                    Saved.
                </p>
            </div>
        </Form>
    </div>
</template>
