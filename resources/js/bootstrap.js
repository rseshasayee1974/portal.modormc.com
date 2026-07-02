import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

window.axios.interceptors.response.use(
    response => response,
    error => {
        if (error.response && [401, 419].includes(error.response.status)) {
            if (window.location.pathname === '/login') {
                window.location.reload();
            } else {
                window.location.href = '/login';
            }
        }
        return Promise.reject(error);
    }
);
