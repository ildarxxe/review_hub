import { computed, ref } from 'vue';
import api from '../api';

const user = ref(null);
const loading = ref(true);

export function useAuth() {
    const authenticated = computed(() => user.value !== null);

    async function restoreSession() {
        loading.value = true;

        try {
            const response = await api.get('/api/user');
            user.value = response.data.user;
        } catch (error) {
            if (error.response?.status !== 401) {
                throw error;
            }

            user.value = null;
        } finally {
            loading.value = false;
        }
    }

    async function login(credentials) {
        await api.get('/sanctum/csrf-cookie');
        const response = await api.post('/api/login', credentials);
        user.value = response.data.user;
    }

    async function logout() {
        await api.post('/api/logout');
        user.value = null;
    }

    return {
        authenticated,
        loading,
        login,
        logout,
        restoreSession,
        user,
    };
}
