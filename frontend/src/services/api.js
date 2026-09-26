import axios from 'axios';

const TOKEN_KEY = 'tmc_token';

export const getToken = () => localStorage.getItem(TOKEN_KEY);
export const setToken = (token) => localStorage.setItem(TOKEN_KEY, token);
// Listeners (NotificationContext, realtime lifecycle) react to logout/401.
export const clearToken = () => {
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem('tmc_user');
  window.dispatchEvent(new Event('tmc:auth-changed'));
};

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' }
});

api.interceptors.request.use((config) => {
  const token = getToken();
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

// Expired/revoked tokens previously left pages spinning forever — every request
// failed but nothing redirected to login. Centralize that here.
api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status;
    const url = error.config?.url || '';
    const isAuthRequest = url.includes('/login') || url.includes('/register') || url.includes('/logout');

    if (status === 401 && !isAuthRequest) {
      clearToken();
      // Avoid a redirect loop if we're already heading to the login page.
      if (window.location.pathname !== '/login') {
        window.location.replace('/login');
      }
    }
    return Promise.reject(error);
  }
);

export default api;
