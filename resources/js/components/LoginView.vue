<script setup>
import { reactive, ref } from 'vue';
import { useAuth } from '../composables/useAuth';

const { login } = useAuth();
const form = reactive({
    login: '',
    password: '',
});
const fieldErrors = ref({});
const errorMessage = ref('');
const submitting = ref(false);

async function submit() {
    fieldErrors.value = {};
    errorMessage.value = '';
    submitting.value = true;

    try {
        await login(form);
    } catch (error) {
        if (error.response?.status === 422) {
            fieldErrors.value = error.response.data.errors ?? {};
        } else {
            errorMessage.value = 'Не удалось выполнить вход. Попробуйте ещё раз.';
        }
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <main class="login-page">
        <section class="login-intro">
            <a href="/" class="brand">
                <span class="brand-mark">R</span>
                ReviewHub
            </a>

            <div class="intro-copy">
                <span class="eyebrow">Отзывы под контролем</span>
                <h1>Репутация компании в одном окне.</h1>
                <p>
                    Подключите карточку на Яндекс.Картах и следите за рейтингом
                    и обратной связью клиентов.
                </p>
            </div>
        </section>

        <section class="login-panel">
            <form class="login-form" @submit.prevent="submit">
                <div>
                    <span class="eyebrow">Добро пожаловать</span>
                    <h2>Вход в аккаунт</h2>
                    <p class="muted">Введите данные администратора, чтобы продолжить.</p>
                </div>

                <div v-if="errorMessage" class="alert" role="alert">
                    {{ errorMessage }}
                </div>

                <label class="field">
                    <span>Логин</span>
                    <input
                        v-model.trim="form.login"
                        name="login"
                        autocomplete="username"
                        placeholder="admin"
                        :aria-invalid="Boolean(fieldErrors.login)"
                    >
                    <small v-if="fieldErrors.login">{{ fieldErrors.login[0] }}</small>
                </label>

                <label class="field">
                    <span>Пароль</span>
                    <input
                        v-model="form.password"
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        placeholder="Введите пароль"
                        :aria-invalid="Boolean(fieldErrors.password)"
                    >
                    <small v-if="fieldErrors.password">{{ fieldErrors.password[0] }}</small>
                </label>

                <button class="button button-primary" type="submit" :disabled="submitting">
                    {{ submitting ? 'Входим...' : 'Войти' }}
                </button>
            </form>
        </section>
    </main>
</template>
