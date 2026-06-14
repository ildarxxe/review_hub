<script setup>
import { onMounted, ref } from 'vue';
import LoginView from './components/LoginView.vue';
import SettingsView from './components/SettingsView.vue';
import { useAuth } from './composables/useAuth';

const { authenticated, loading, restoreSession } = useAuth();
const startupError = ref('');

async function initialize() {
    startupError.value = '';

    try {
        await restoreSession();
    } catch {
        startupError.value = 'Не удалось связаться с сервером. Обновите страницу.';
    }
}

onMounted(initialize);
</script>

<template>
    <main v-if="loading" class="screen-center" aria-live="polite">
        <div class="loader"></div>
        <p>Загружаем приложение...</p>
    </main>

    <main v-else-if="startupError" class="screen-center">
        <div class="error-card">
            <h1>Сервис недоступен</h1>
            <p>{{ startupError }}</p>
            <button type="button" class="button button-primary" @click="initialize">
                Попробовать снова
            </button>
        </div>
    </main>

    <SettingsView v-else-if="authenticated" />
    <LoginView v-else />
</template>
