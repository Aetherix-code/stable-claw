<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BookOpen, Brain, CalendarClock, FolderGit2, LayoutGrid, Library, Plug, Settings } from 'lucide-vue-next';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { edit as editConnections } from '@/routes/connections';
import { edit as editData } from '@/routes/data';
import { index as scheduledJobsIndex } from '@/routes/scheduled-jobs';
import { index as secretaryIndex } from '@/routes/secretary/chat';
import { index as skillsIndex } from '@/routes/secretary/skills';
import { index as memoriesIndex } from '@/routes/secretary/memories';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const secretaryNavItems: NavItem[] = [
    {
        title: 'Chat',
        href: secretaryIndex(),
        icon: Brain,
    },
    {
        title: 'Skills',
        href: skillsIndex(),
        icon: Library,
    },
    {
        title: 'Memory',
        href: memoriesIndex(),
        icon: BookOpen,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];

const systemNavItems: NavItem[] = [
    {
        title: 'Connections',
        href: editConnections(),
        icon: Plug,
    },
    {
        title: 'Scheduled Jobs',
        href: scheduledJobsIndex(),
        icon: CalendarClock,
    },
    {
        title: 'Settings',
        href: editData(),
        icon: Settings,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
            <NavMain :items="secretaryNavItems" label="Secretary" />
            <NavMain :items="systemNavItems" label="System" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

