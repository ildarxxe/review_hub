import { ref } from 'vue';
import api from '../api';

const organization = ref(null);
const loading = ref(false);

export function useOrganization() {
    async function fetchOrganization({ silent = false } = {}) {
        if (!silent) {
            loading.value = true;
        }

        try {
            const response = await api.get('/api/organization');
            organization.value = response.data.organization;

            return organization.value;
        } finally {
            if (!silent) {
                loading.value = false;
            }
        }
    }

    async function saveOrganization(url) {
        const response = await api.put('/api/organization', { url });
        organization.value = response.data.organization;

        return organization.value;
    }

    return {
        fetchOrganization,
        loading,
        organization,
        saveOrganization,
    };
}
