import axios from 'axios';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

window.axios.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error.response?.status;
        if (status === 419 || status === 401) {
            window.location.reload();
            return new Promise(() => {});
        }
        return Promise.reject(error);
    },
);
