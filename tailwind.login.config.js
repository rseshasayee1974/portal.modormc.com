import applicationConfig from './tailwind.config.js';

export default {
    ...applicationConfig,
    important: '#login-page',
    content: [
        './resources/js/Pages/Auth/Login.vue',
        './resources/js/Components/Auth/LoginPassword.vue',
        './resources/js/Components/GlobalLoader.vue',
    ],
};
