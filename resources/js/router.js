import { createRouter, createWebHistory } from 'vue-router';
import { auth, fetchUser } from './auth';
import { setUnauthorizedHandler } from './api';
import AppLayout from './layouts/AppLayout.vue';
import Dashboard from './pages/Dashboard.vue';
import Login from './pages/Login.vue';
import ComingSoon from './pages/ComingSoon.vue';
import SettingTarget from './pages/SettingTarget.vue';
import { menu } from './menu';

const pages = { dashboard: Dashboard, 'setting-target': SettingTarget };

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/login', name: 'login', component: Login, meta: { guest: true } },
        {
            path: '/',
            component: AppLayout,
            children: [
                { path: '', redirect: '/dashboard' },
                ...menu.map((item) => ({
                    path: item.path.slice(1),
                    component: pages[item.page] ?? ComingSoon,
                    meta: { title: item.label },
                })),
            ],
        },
        { path: '/:pathMatch(.*)*', redirect: '/dashboard' },
    ],
});

router.beforeEach(async (to) => {
    if (!auth.checked) {
        await fetchUser();
    }
    if (!to.meta.guest && !auth.user) {
        return { name: 'login', query: to.fullPath !== '/dashboard' ? { redirect: to.fullPath } : {} };
    }
    if (to.meta.guest && auth.user) {
        return '/dashboard';
    }
    document.title = to.meta.title ? `${to.meta.title} · Marketama Motoris Analytics` : 'Marketama Motoris Analytics';
});

setUnauthorizedHandler(() => {
    auth.user = null;
    if (router.currentRoute.value.name !== 'login') {
        router.push({ name: 'login' });
    }
});

export default router;
