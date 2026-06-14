<script setup>
import { ref } from 'vue';
import { useAuth } from '../composables/useAuth';

const { logout, user } = useAuth();
const loggingOut = ref(false);
const logoutError = ref('');

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
            <span class="eyebrow">Настройки</span>
            <h1>Подключение организации</h1>
            <p class="muted">
                Здесь появится форма подключения карточки на Яндекс.Картах.
            </p>
        </section>
    </main>
</template>
