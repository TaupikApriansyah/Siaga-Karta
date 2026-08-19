import axios from 'axios';

const TOKEN_KEY='siagakarta_token';
const META_KEY='siagakarta_auth_meta';
const CHANNEL_NAME='siagakarta-auth-v1';
const channel=typeof BroadcastChannel!=='undefined' ? new BroadcastChannel(CHANNEL_NAME) : null;

const api = axios.create({ baseURL: '/api', headers: { Accept: 'application/json' }, timeout: 30000 });

api.interceptors.request.use((config) => {
  const token = sessionStorage.getItem(TOKEN_KEY);
  if (token) config.headers.Authorization = `Bearer ${token}`;
  config.headers['X-Request-Id'] = config.headers['X-Request-Id'] || globalThis.crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}`;
  return config;
});

api.interceptors.response.use(
  response => response,
  error => {
    if(error?.response?.status===401 && sessionStorage.getItem(TOKEN_KEY)) {
      window.dispatchEvent(new CustomEvent('siagakarta:unauthorized'));
    }
    return Promise.reject(error);
  }
);

export const setToken = (token, meta=null, {broadcast=true}={}) => {
  if (token) {
    sessionStorage.setItem(TOKEN_KEY, token);
    if(meta) sessionStorage.setItem(META_KEY, JSON.stringify(meta));
    if(broadcast && channel) channel.postMessage({type:'TOKEN_SET',token,meta});
  } else {
    sessionStorage.removeItem(TOKEN_KEY);
    sessionStorage.removeItem(META_KEY);
    if(broadcast && channel) channel.postMessage({type:'LOGOUT'});
  }
};
export const getToken = () => sessionStorage.getItem(TOKEN_KEY);
export const getAuthMeta = () => { try{return JSON.parse(sessionStorage.getItem(META_KEY)||'{}');}catch{return {};} };
export const setAuthMeta = (meta) => sessionStorage.setItem(META_KEY,JSON.stringify(meta||{}));
export const newRequestUuid = () => {
  if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID();
  const bytes = new Uint8Array(16);
  globalThis.crypto?.getRandomValues?.(bytes);
  if (!bytes.some(Boolean)) for (let i=0;i<bytes.length;i++) bytes[i]=Math.floor(Math.random()*256);
  bytes[6]=(bytes[6]&0x0f)|0x40; bytes[8]=(bytes[8]&0x3f)|0x80;
  const h=[...bytes].map(v=>v.toString(16).padStart(2,'0')).join('');
  return `${h.slice(0,8)}-${h.slice(8,12)}-${h.slice(12,16)}-${h.slice(16,20)}-${h.slice(20)}`;
};
export const requestSharedSession = () => channel?.postMessage({type:'SESSION_REQUEST'});
export const broadcastSession = () => { const token=getToken(); if(token) channel?.postMessage({type:'SESSION_RESPONSE',token,meta:getAuthMeta()}); };
export const subscribeAuth = (handler) => {
  if(!channel) return () => {};
  const listener=(event)=>handler(event.data||{});
  channel.addEventListener('message',listener);
  return ()=>channel.removeEventListener('message',listener);
};

const readableValidation = (value) => {
  const text=String(value||'').trim();
  if(!text) return '';
  if(/^validation(?:\.|$)/i.test(text)) return 'Data belum memenuhi ketentuan formulir. Periksa isian yang ditandai lalu coba lagi.';
  return text;
};

export const errorMessage = (error) => {
  const data = error?.response?.data;
  if (data?.errors) {
    const messages=Object.values(data.errors).flat().map(readableValidation).filter(Boolean);
    if(messages.length) return [...new Set(messages)].join(' ');
  }
  if(error?.response?.status===422) return readableValidation(data?.message) || 'Data belum valid. Periksa kembali formulir.';
  if(error?.response?.status===429) return 'Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi.';
  if(error?.response?.status>=500) return 'Server belum dapat memproses data. Coba lagi. Jika berulang, periksa log aplikasi.';
  if(error?.code==='ECONNABORTED') return 'Permintaan terlalu lama. Periksa koneksi lalu coba lagi.';
  return readableValidation(data?.message) || error?.message || 'Terjadi kesalahan pada sistem.';
};
export default api;
