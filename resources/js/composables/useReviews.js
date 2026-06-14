import { ref } from 'vue';
import api from '../api';

const reviews = ref([]);
const meta = ref(null);
const loading = ref(false);

export function useReviews() {
    function clearReviews() {
        reviews.value = [];
        meta.value = null;
    }

    async function fetchReviews(page = 1) {
        loading.value = true;

        try {
            const response = await api.get('/api/reviews', {
                params: { page },
            });
            reviews.value = response.data.data;
            meta.value = response.data.meta;

            return response.data;
        } finally {
            loading.value = false;
        }
    }

    return {
        clearReviews,
        fetchReviews,
        loading,
        meta,
        reviews,
    };
}
