<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Download, Upload } from 'lucide-vue-next';
import { computed } from 'vue';
import DataController from '@/actions/App/Http/Controllers/Settings/DataController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { edit } from '@/routes/data';

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
    <Head title="Data settings" />

    <h1 class="sr-only">Data settings</h1>

    <div class="space-y-6">
        <div>
            <Heading
                variant="small"
                title="Export data"
                description="Download a complete backup of your database as a SQLite file"
            />

            <a :href="DataController.exportMethod.url()">
                <Button type="button">
                    <Download class="mr-2 h-4 w-4" />
                    Download backup
                </Button>
            </a>
        </div>

        <div>
            <Heading
                variant="small"
                title="Import data"
                description="Restore from a previously exported SQLite backup file. This will replace all current data."
            />

            <Alert v-if="success" class="mb-4">
                <AlertTitle>Success</AlertTitle>
                <AlertDescription>{{ success }}</AlertDescription>
            </Alert>

            <Form
                v-bind="DataController.importMethod.form()"
                enctype="multipart/form-data"
                class="space-y-4"
                v-slot="{ errors, processing }"
            >
                <Input
                    type="file"
                    name="file"
                    accept=".sqlite"
                    class="max-w-sm"
                />

                <InputError :message="errors.file" />

                <Button type="submit" :disabled="processing">
                    <Upload class="mr-2 h-4 w-4" />
                    {{ processing ? 'Restoring...' : 'Restore backup' }}
                </Button>
            </Form>
        </div>
    </div>
</template>
