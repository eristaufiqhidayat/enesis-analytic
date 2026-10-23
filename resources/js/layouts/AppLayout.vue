<template>
    <div class="min-h-screen">
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur dark:border-slate-800 dark:bg-slate-900/95">
            <div class="flex h-14 items-center gap-4 px-4 lg:px-8">
                <RouterLink to="/dashboard" class="flex shrink-0 items-center gap-2">
                    <span class="flex h-7 w-7 items-center justify-center rounded-md bg-brand-600 text-white">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                            <path d="M4 20V10M10 20V4M16 20v-7M22 20H2" />
                        </svg>
                    </span>
                    <span class="text-[15px] font-bold tracking-tight">Marketama Motoris Analytics</span>
                </RouterLink>

                <nav class="hidden flex-1 items-center gap-1 overflow-x-auto xl:flex">
                    <RouterLink
                        v-for="item in menu"
                        :key="item.path"
                        :to="item.path"
                        class="rounded-lg px-3 py-1.5 text-sm leading-tight font-medium text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                        active-class="!bg-brand-50 !text-brand-600 dark:!bg-brand-700/30 dark:!text-brand-100"
                    >
                        <span class="block max-w-[7.5rem]">{{ item.label }}</span>
                    </RouterLink>
                </nav>

                <div class="ml-auto flex items-center gap-3">
                    <button type="button" class="rounded-md p-1.5 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800" :title="dark ? 'Mode terang' : 'Mode gelap'" @click="toggleTheme">
                        <svg v-if="!dark" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z" /></svg>
                        <svg v-else viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" /></svg>
                    </button>
                    <div class="flex items-center gap-2 border-l border-slate-200 pl-3 dark:border-slate-700">
                        <span class="flex h-7 w-7 items-center justify-center rounded-md bg-brand-500 text-xs font-semibold text-white">{{ initial }}</span>
                        <span class="hidden text-sm font-medium sm:inline">{{ auth.user?.username ?? auth.user?.name }}</span>
                    </div>
                    <button type="button" class="rounded-md p-1.5 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800" title="Keluar" @click="doLogout">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" /></svg>
                    </button>
                    <button type="button" class="rounded-md p-1.5 hover:bg-slate-100 xl:hidden dark:hover:bg-slate-800" @click="open = !open">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18" /></svg>
                    </button>
                </div>
            </div>
            <nav v-if="open" class="grid grid-cols-2 gap-1 border-t border-slate-200 p-3 sm:grid-cols-3 xl:hidden dark:border-slate-800">
                <RouterLink
                    v-for="item in menu"
                    :key="item.path"
                    :to="item.path"
                    class="rounded-lg px-3 py-2 text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-800"
                    active-class="!bg-brand-50 !text-brand-600 dark:!bg-brand-700/30"
                    @click="open = false"
                >
                    {{ item.label }}
                </RouterLink>
            </nav>
        </header>

        <main class="px-4 py-6 lg:px-6">
            <RouterView />
        </main>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { menu } from '../menu';
import { auth, logout } from '../auth';

const router = useRouter();
const open = ref(false);
const dark = ref(document.documentElement.classList.contains('dark'));
const initial = computed(() => (auth.user?.username ?? auth.user?.name ?? 'U').charAt(0).toUpperCase());

function toggleTheme() {
    dark.value = !dark.value;
    document.documentElement.classList.toggle('dark', dark.value);
    try {
        localStorage.setItem('theme', dark.value ? 'dark' : 'light');
    } catch {
        // abaikan bila storage tidak tersedia
    }
    window.dispatchEvent(new Event('themechange'));
}

async function doLogout() {
    await logout();
    router.push({ name: 'login' });
}
</script>
