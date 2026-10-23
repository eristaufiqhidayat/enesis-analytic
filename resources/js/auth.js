import { reactive } from 'vue';
import api from './api';

export const auth = reactive({
    user: null,
    checked: false,
});

export async function fetchUser() {
    try {
        const { data } = await api.get('/auth/me');
        auth.user = data.data;
    } catch {
        auth.user = null;
    }
    auth.checked = true;
    return auth.user;
}

export async function login(username, password) {
    const { data } = await api.post('/auth/login', { username, password });
    auth.user = data.data;
    return auth.user;
}

export async function logout() {
    try {
        await api.post('/auth/logout');
    } finally {
        auth.user = null;
    }
}
