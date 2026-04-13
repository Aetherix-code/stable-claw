<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { edit as profileEdit } from '@/routes/profile';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const showTelegramWarning = computed(() => !user.value.telegram_username || !user.value.telegram_chat_id);
const warningMessage = computed(() => {
    if (!user.value.telegram_username) return 'username';
    if (!user.value.telegram_chat_id) return 'link';
    return null;
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <div
                v-if="showTelegramWarning"
                class="mx-4 mt-2 rounded-md border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200"
            >
                <template v-if="warningMessage === 'username'">
                    Set your
                    <Link
                        :href="profileEdit()"
                        class="underline underline-offset-2 hover:text-amber-900 dark:hover:text-amber-100"
                    >
                        Telegram username
                    </Link>
                    to receive scheduled job responses via Telegram.
                </template>
                <template v-else>
                    Send a message to the Telegram bot to link your account for scheduled job responses.
                </template>
            </div>
            <slot />
        </AppContent>
    </AppShell>
</template>
