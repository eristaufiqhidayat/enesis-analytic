<template>
    <div class="flex min-h-screen items-center justify-center bg-gradient-to-br from-brand-50 to-slate-100 px-4 dark:from-slate-950 dark:to-slate-900">
        <form class="card w-full max-w-sm p-8" @submit.prevent="submit">
            <div class="mb-6 flex items-center gap-2">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-600 text-white">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2" /></svg>
                </span>
                <div>
                    <h1 class="text-lg font-bold leading-tight">Marketama Motoris Analytics</h1>
                    <p class="text-xs text-slate-500">Masuk untuk melihat dashboard</p>
                </div>
            </div>

            <label class="field-label" for="username">Username</label>
            <input id="username" v-model="username" class="field mb-4 h-10 text-sm" autocomplete="username" required />

            <label class="field-label" for="password">Password</label>
            <input id="password" v-model="password" type="password" class="field mb-2 h-10 text-sm" autocomplete="current-password" required />

            <p v-if="error" class="mb-2 text-xs text-red-500">{{ error }}</p>

            <button type="submit" class="mt-4 h-10 w-full rounded-md bg-brand-600 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-60" :disabled="loading">
                {{ loading ? 'Memproses…' : 'Masuk' }}
            </button>
        </form>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { login } from '../auth';

const router = useRouter();
const route = useRoute();
const username = ref('');
const password = ref('');
const error = ref('');
const loading = ref(false);

async function submit() {
    loading.value = true;
    error.value = '';
    try {
        await login(username.value, password.value);
        router.push(route.query.redirect || '/dashboard');
    } catch (e) {
        error.value = e.response?.data?.errors?.username?.[0] ?? e.response?.data?.message ?? 'Gagal masuk.';
    } finally {
        loading.value = false;
    }
}
</script>
