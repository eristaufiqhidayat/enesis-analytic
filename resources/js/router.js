import { createRouter, createWebHistory } from 'vue-router';
import { auth, fetchUser } from './auth';
import { setUnauthorizedHandler } from './api';
import AppLayout from './layouts/AppLayout.vue';
import Dashboard from './pages/Dashboard.vue';
import Login from './pages/Login.vue';
import ComingSoon from './pages/ComingSoon.vue';
import SettingTarget from './pages/SettingTarget.vue';

export const menu = [
    { path: '/dashboard', label: 'Dashboard', component: Dashboard },
    { path: '/sales-overview', label: 'Sales Overview' },
    { path: '/brand-overview', label: 'Brand Overview' },
    { path: '/heatmap-brand', label: 'Heatmap & Brand' },
    { path: '/trend-sales', label: 'Trend Sales' },
    { path: '/canvasser-overview', label: 'Canvasser Overview' },
    { path: '/data-outlet', label: 'Data Outlet' },
    { path: '/download-data', label: 'Download Data' },
    { path: '/setting-target', label: 'Setting Target', component: SettingTarget },
];

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
                    component: item.component ?? ComingSoon,
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
