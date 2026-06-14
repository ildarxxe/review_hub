<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useAuth } from '../composables/useAuth';
import { useOrganization } from '../composables/useOrganization';
import { useReviews } from '../composables/useReviews';

const { logout, user } = useAuth();
const { fetchOrganization, loading, organization, saveOrganization } = useOrganization();
const {
    clearReviews,
    fetchReviews,
    loading: reviewsLoading,
    meta: reviewsMeta,
    reviews,
} = useReviews();

const url = ref('');
const fieldError = ref('');
const pageError = ref('');
const reviewError = ref('');
const successMessage = ref('');
const saving = ref(false);
const loggingOut = ref(false);
const logoutError = ref('');
const reviewsSection = ref(null);
let pollTimer = null;

const status = computed(() => {
    const statuses = {
        failed: {
            label: 'Ошибка синхронизации',
            className: 'status-error',
        },
        pending: {
            label: 'Ожидает синхронизации',
            className: 'status-pending',
        },
        ready: {
            label: 'Данные обновлены',
            className: 'status-ready',
        },
        syncing: {
            label: 'Загружаем данные',
            className: 'status-syncing',
        },
    };

    return statuses[organization.value?.sync_status] ?? statuses.pending;
});

const pages = computed(() => {
    if (!reviewsMeta.value || reviewsMeta.value.last_page <= 1) {
        return [];
    }

    return Array.from({ length: reviewsMeta.value.last_page }, (_, index) => index + 1);
});

function stopPolling() {
    if (pollTimer) {
        window.clearTimeout(pollTimer);
        pollTimer = null;
    }
}

function schedulePoll() {
    stopPolling();
    pollTimer = window.setTimeout(pollOrganization, 3000);
}

async function pollOrganization() {
    try {
        const current = await fetchOrganization({ silent: true });
        pageError.value = '';

        if (current?.sync_status === 'ready') {
            successMessage.value = 'Отзывы и данные организации обновлены.';
            await loadReviews(1);
            return;
        }

        if (current?.sync_status === 'failed') {
            successMessage.value = '';
            return;
        }

        schedulePoll();
    } catch {
        pageError.value = 'Не удалось проверить статус синхронизации.';
    }
}

async function loadOrganization() {
    pageError.value = '';

    try {
        const current = await fetchOrganization();
        url.value = current?.source_url ?? '';

        if (current?.sync_status === 'ready') {
            await loadReviews(1);
        } else if (current && ['pending', 'syncing'].includes(current.sync_status)) {
            schedulePoll();
        }
    } catch {
        pageError.value = 'Не удалось загрузить настройки организации.';
    }
}

async function loadReviews(page) {
    reviewError.value = '';

    try {
        await fetchReviews(page);
    } catch {
        reviewError.value = 'Не удалось загрузить отзывы. Попробуйте ещё раз.';
    }
}

async function changePage(page) {
    if (page === reviewsMeta.value?.current_page || reviewsLoading.value) {
        return;
    }

    await loadReviews(page);
    reviewsSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function submit() {
    fieldError.value = '';
    pageError.value = '';
    reviewError.value = '';
    successMessage.value = '';
    saving.value = true;
    stopPolling();

    try {
        const saved = await saveOrganization(url.value);
        url.value = saved.source_url;
        clearReviews();
        successMessage.value = 'Ссылка сохранена. Загружаем данные и отзывы.';
        schedulePoll();
    } catch (error) {
        if (error.response?.status === 422) {
            fieldError.value = error.response.data.errors?.url?.[0] ?? 'Проверьте ссылку.';
        } else {
            pageError.value = 'Не удалось сохранить настройки. Попробуйте ещё раз.';
        }
    } finally {
        saving.value = false;
    }
}

async function signOut() {
    loggingOut.value = true;
    logoutError.value = '';
    stopPolling();

    try {
        await logout();
    } catch {
        logoutError.value = 'Не удалось выйти. Попробуйте ещё раз.';
    } finally {
        loggingOut.value = false;
    }
}

function formatDate(date) {
    return new Intl.DateTimeFormat('ru-RU', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(new Date(date));
}

function formatNumber(value) {
    return new Intl.NumberFormat('ru-RU').format(value);
}

onMounted(loadOrganization);
onBeforeUnmount(stopPolling);
</script>

<template>
    <main class="app-shell">
        <header class="app-header">
            <a href="/" class="brand">
                <span class="brand-mark">R</span>
                ReviewHub
            </a>

            <div class="user-actions">
                <span>{{ user.name }}</span>
                <button class="button button-secondary" type="button" :disabled="loggingOut" @click="signOut">
                    {{ loggingOut ? 'Выходим...' : 'Выйти' }}
                </button>
            </div>
        </header>

        <section class="content">
            <div v-if="logoutError" class="alert" role="alert">{{ logoutError }}</div>

            <div class="page-heading">
                <div>
                    <span class="eyebrow">Настройки</span>
                    <h1>Подключение организации</h1>
                    <p class="muted">Вставьте ссылку на карточку компании в Яндекс.Картах.</p>
                </div>
            </div>

            <div v-if="pageError" class="alert page-alert" role="alert">
                <span>{{ pageError }}</span>
                <button v-if="!organization" type="button" class="alert-action" @click="loadOrganization">
                    Повторить
                </button>
            </div>

            <div v-if="successMessage" class="notice" role="status">
                {{ successMessage }}
            </div>

            <section class="settings-card">
                <div class="card-heading">
                    <div>
                        <h2>Ссылка на Яндекс.Карты</h2>
                        <p>Откройте карточку организации в браузере и скопируйте адрес страницы.</p>
                    </div>
                    <span class="source-badge">Яндекс.Карты</span>
                </div>

                <div v-if="loading" class="inline-loading" aria-live="polite">
                    <span class="loader loader-small"></span>
                    Загружаем настройки...
                </div>

                <form v-else class="organization-form" @submit.prevent="submit">
                    <label class="field">
                        <span>Ссылка на карточку</span>
                        <input
                            v-model.trim="url"
                            type="url"
                            name="organization_url"
                            autocomplete="url"
                            placeholder="https://yandex.ru/maps/org/..."
                            :aria-invalid="Boolean(fieldError)"
                            :disabled="saving"
                        >
                        <small v-if="fieldError">{{ fieldError }}</small>
                        <small v-else class="field-hint">
                            Поддерживаются ссылки с доменов yandex.ru, yandex.com, yandex.kz и других регионов.
                        </small>
                    </label>

                    <button class="button button-primary" type="submit" :disabled="saving || !url">
                        {{ saving ? 'Сохраняем...' : 'Сохранить и загрузить отзывы' }}
                    </button>
                </form>
            </section>

            <section v-if="organization" class="organization-card">
                <div class="organization-summary">
                    <div>
                        <span class="eyebrow">Подключённая карточка</span>
                        <h2>{{ organization.name || 'Организация на Яндекс.Картах' }}</h2>
                        <a :href="organization.source_url" target="_blank" rel="noopener noreferrer">
                            Открыть карточку
                        </a>
                    </div>
                    <span class="status-badge" :class="status.className">
                        <span class="status-dot"></span>
                        {{ status.label }}
                    </span>
                </div>

                <div v-if="organization.sync_status === 'failed'" class="alert">
                    {{ organization.sync_error || 'Не удалось получить данные организации.' }}
                </div>

                <div v-if="['pending', 'syncing'].includes(organization.sync_status)" class="sync-progress">
                    <span class="loader loader-small"></span>
                    Парсер загружает доступные отзывы. Для большой карточки это может занять несколько минут.
                </div>

                <div class="metrics-grid">
                    <article>
                        <span>Средний рейтинг</span>
                        <strong>{{ organization.rating ?? '—' }}</strong>
                    </article>
                    <article>
                        <span>Количество оценок</span>
                        <strong>{{ organization.ratings_count == null ? '—' : formatNumber(organization.ratings_count) }}</strong>
                    </article>
                    <article>
                        <span>Количество отзывов</span>
                        <strong>{{ organization.reviews_count == null ? '—' : formatNumber(organization.reviews_count) }}</strong>
                    </article>
                </div>
            </section>

            <section
                v-if="organization?.sync_status === 'ready'"
                ref="reviewsSection"
                class="reviews-section"
            >
                <div class="reviews-heading">
                    <div>
                        <span class="eyebrow">Отзывы</span>
                        <h2>Отзывы клиентов</h2>
                        <p v-if="reviewsMeta" class="muted">
                            Загружено {{ formatNumber(reviewsMeta.total) }} из {{ formatNumber(organization.reviews_count) }} доступных отзывов.
                        </p>
                    </div>
                    <span v-if="reviewsMeta" class="reviews-count">
                        Страница {{ reviewsMeta.current_page }} из {{ reviewsMeta.last_page }}
                    </span>
                </div>

                <div v-if="reviewError" class="alert page-alert" role="alert">
                    <span>{{ reviewError }}</span>
                    <button type="button" class="alert-action" @click="loadReviews(reviewsMeta?.current_page || 1)">
                        Повторить
                    </button>
                </div>

                <div v-if="reviewsLoading" class="reviews-loading" aria-live="polite">
                    <span class="loader"></span>
                    Загружаем отзывы...
                </div>

                <div v-else-if="reviews.length" class="reviews-list">
                    <article v-for="review in reviews" :key="review.id" class="review-card">
                        <div class="review-header">
                            <div class="review-author">
                                <span class="author-avatar">{{ review.author.charAt(0).toUpperCase() }}</span>
                                <div>
                                    <strong>{{ review.author }}</strong>
                                    <time :datetime="review.date">{{ formatDate(review.date) }}</time>
                                </div>
                            </div>
                            <span class="review-rating" :aria-label="`Оценка ${review.rating} из 5`">
                                <span aria-hidden="true">★</span>
                                {{ review.rating }}
                            </span>
                        </div>
                        <p :class="{ 'review-empty': !review.text }">
                            {{ review.text || 'Пользователь оставил оценку без текста.' }}
                        </p>
                    </article>
                </div>

                <div v-else class="empty-reviews">
                    У этой карточки пока нет доступных отзывов.
                </div>

                <nav v-if="pages.length && !reviewsLoading" class="pagination" aria-label="Страницы отзывов">
                    <button
                        type="button"
                        :disabled="reviewsMeta.current_page === 1"
                        aria-label="Предыдущая страница"
                        @click="changePage(reviewsMeta.current_page - 1)"
                    >
                        ←
                    </button>
                    <button
                        v-for="page in pages"
                        :key="page"
                        type="button"
                        :class="{ active: page === reviewsMeta.current_page }"
                        :aria-current="page === reviewsMeta.current_page ? 'page' : undefined"
                        @click="changePage(page)"
                    >
                        {{ page }}
                    </button>
                    <button
                        type="button"
                        :disabled="reviewsMeta.current_page === reviewsMeta.last_page"
                        aria-label="Следующая страница"
                        @click="changePage(reviewsMeta.current_page + 1)"
                    >
                        →
                    </button>
                </nav>
            </section>
        </section>
    </main>
</template>
