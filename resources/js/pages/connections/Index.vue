<script setup lang="ts">
import { Form, Head, router, useHttp, usePage } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ConnectionController from '@/actions/App/Http/Controllers/Settings/ConnectionController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
import { edit } from '@/routes/connections';

type Connection = {
    id: number;
    type: string;
    provider: string;
    name: string;
    email: string;
    is_active: boolean;
    created_at: string;
};

type Props = {
    connections: Connection[];
};

defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Connections',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const success = computed(() => page.props.flash?.success as string | undefined);

const showAddDialog = ref(false);
const testingId = ref<number | null>(null);
const testResult = ref<{ success: boolean; message: string } | null>(null);

const http = useHttp();

const testConnection = async (connection: Connection) => {
    testingId.value = connection.id;
    testResult.value = null;

    try {
        const result = (await http.submit(ConnectionController.test(connection.id))) as {
            success: boolean;
            message: string;
        };
        testResult.value = result;
    } catch {
        testResult.value = { success: false, message: 'Connection test failed.' };
    } finally {
        testingId.value = null;
    }
};

const toggleActive = (connection: Connection) => {
    router.patch(
        ConnectionController.update.url(connection.id),
        { is_active: !connection.is_active },
        { preserveScroll: true },
    );
};

const deleteConnection = (connection: Connection) => {
    router.delete(ConnectionController.destroy.url(connection.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Connections" />

    <div class="mx-auto w-full max-w-4xl px-4 py-6">
        <h1 class="sr-only">Connections</h1>

        <div class="space-y-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Connections"
                description="Connect external services like email to your secretary"
            />

            <Dialog v-model:open="showAddDialog">
                <DialogTrigger as-child>
                    <Button size="sm">
                        <Plus class="mr-2 h-4 w-4" />
                        Add connection
                    </Button>
                </DialogTrigger>

                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add mail connection</DialogTitle>
                        <DialogDescription>
                            Connect a Gmail account using an App Password. Enable 2-Step Verification first, then
                            generate one at myaccount.google.com/apppasswords.
                        </DialogDescription>
                    </DialogHeader>

                    <Form
                        v-bind="ConnectionController.store.form()"
                        class="space-y-4"
                        v-slot="{ errors, processing }"
                        @success="showAddDialog = false"
                    >
                        <div class="grid gap-2">
                            <Label for="provider">Provider</Label>
                            <Select name="provider" default-value="gmail">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select provider" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="gmail">Gmail</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.provider" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="name">Name</Label>
                            <Input id="name" name="name" placeholder="My Gmail" />
                            <InputError :message="errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="email">Email address</Label>
                            <Input id="email" name="email" type="email" placeholder="you@gmail.com" />
                            <InputError :message="errors.email" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="password">App Password</Label>
                            <Input id="password" name="password" type="password" placeholder="xxxx xxxx xxxx xxxx" autocomplete="off" />
                            <p class="text-sm text-muted-foreground">
                                A 16-character app password from your Google account security settings.
                            </p>
                            <InputError :message="errors.password" />
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" @click="showAddDialog = false">Cancel</Button>
                            <Button type="submit" :disabled="processing">
                                {{ processing ? 'Adding...' : 'Add connection' }}
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>

        <Alert v-if="success" class="mb-4">
            <AlertTitle>Success</AlertTitle>
            <AlertDescription>{{ success }}</AlertDescription>
        </Alert>

        <Alert v-if="testResult" :class="testResult.success ? '' : 'border-destructive'">
            <AlertTitle>{{ testResult.success ? 'Test passed' : 'Test failed' }}</AlertTitle>
            <AlertDescription>{{ testResult.message }}</AlertDescription>
        </Alert>

        <div v-if="connections.length === 0" class="rounded-lg border border-dashed p-8 text-center">
            <p class="text-sm text-muted-foreground">No connections configured yet. Add one to get started.</p>
        </div>

        <div class="space-y-4">
            <Card v-for="connection in connections" :key="connection.id">
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <div>
                        <CardTitle class="text-base">{{ connection.name }}</CardTitle>
                        <CardDescription>{{ connection.email }}</CardDescription>
                    </div>
                    <div class="flex items-center gap-2">
                        <Badge :variant="connection.is_active ? 'default' : 'secondary'">
                            {{ connection.is_active ? 'Active' : 'Inactive' }}
                        </Badge>
                        <Badge variant="outline">{{ connection.provider }}</Badge>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-muted-foreground">Added {{ connection.created_at }}</p>
                        <div class="flex items-center gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                :disabled="testingId === connection.id"
                                @click="testConnection(connection)"
                            >
                                {{ testingId === connection.id ? 'Testing...' : 'Test' }}
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                @click="toggleActive(connection)"
                            >
                                {{ connection.is_active ? 'Disable' : 'Enable' }}
                            </Button>
                            <Button
                                size="sm"
                                variant="destructive"
                                @click="deleteConnection(connection)"
                            >
                                Remove
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
        </div>
    </div>
</template>
