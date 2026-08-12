import axios from 'axios';
const api = axios.create({ baseURL: '/api', headers: { Accept: 'application/json' } });
api.interceptors.request.use((config) => {
  const token = sessionStorage.getItem('siagakarta_token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});
export const setToken = (token) => token ? sessionStorage.setItem('siagakarta_token', token) : sessionStorage.removeItem('siagakarta_token');
export const getToken = () => sessionStorage.getItem('siagakarta_token');
export const errorMessage = (error) => {
  const data = error?.response?.data;
  if (data?.errors) return Object.values(data.errors).flat().join(' ');
  return data?.message || error?.message || 'Terjadi kesalahan pada sistem.';
};
export default api;
