<script setup>
import { computed, onMounted, ref } from 'vue';
import { useAuth } from '../composables/useAuth';
import { useOrganization } from '../composables/useOrganization';

const { logout, user } = useAuth();
const { fetchOrganization, loading, organization, saveOrganization } = useOrganization();
const url = ref('');
const fieldError = ref('');
const pageError = ref('');
const successMessage = ref('');
const saving = ref(false);
const loggingOut = ref(false);
const logoutError = ref('');

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

async function loadOrganization() {
    pageError.value = '';

    try {
        const current = await fetchOrganization();
        url.value = current?.source_url ?? '';
    } catch {
        pageError.value = 'Не удалось загрузить настройки организации.';
    }
}

async function submit() {
    fieldError.value = '';
    pageError.value = '';
    successMessage.value = '';
    saving.value = true;

    try {
        const saved = await saveOrganization(url.value);
        url.value = saved.source_url;
        successMessage.value = 'Ссылка сохранена. Карточка ожидает синхронизации.';
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

    try {
        await logout();
    } catch {
        logoutError.value = 'Не удалось выйти. Попробуйте ещё раз.';
    } finally {
        loggingOut.value = false;
    }
}

onMounted(loadOrganization);
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
                    <p class="muted">
                        Вставьте ссылку на карточку компании в Яндекс.Картах.
                    </p>
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

                <div class="metrics-grid">
                    <article>
                        <span>Средний рейтинг</span>
                        <strong>{{ organization.rating ?? '—' }}</strong>
                    </article>
                    <article>
                        <span>Количество оценок</span>
                        <strong>{{ organization.ratings_count ?? '—' }}</strong>
                    </article>
                    <article>
                        <span>Количество отзывов</span>
                        <strong>{{ organization.reviews_count ?? '—' }}</strong>
                    </article>
                </div>
            </section>
        </section>
    </main>
</template>
