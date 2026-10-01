import axios from 'axios';

let instance = null;

export default class ApiService {

  constructor() {
    this.client = axios.create({
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
      },
    });
    this.client.interceptors.response.use(response => response, error => {
      const payload = error.response?.data;
      if (payload?.error) {
        payload.errors ??= payload.error.fields ?? {};
        payload.message ??= payload.error.message;
      }
      return Promise.reject(error);
    });
  }


  static getInstance() {
    if (!instance) {
      instance = new ApiService();
    }
    return instance;
  }


  setAccessToken(token) {
    this.client.defaults.headers.common.Authorization = `Bearer ${token}`;
  }


  clearAccessToken() {
    delete this.client.defaults.headers.common.Authorization;
  }

  setOrganizationId(id) {
    if (id) this.client.defaults.headers.common['X-Organization-Id'] = String(id);
    else delete this.client.defaults.headers.common['X-Organization-Id'];
  }


  get(url, config = {}) {
    return this.client.get(url, config);
  }


  post(url, data = {}, config = {}) {
    return this.client.post(url, data, config);
  }


  put(url, data = {}, config = {}) {
    return this.client.put(url, data, config);
  }

  patch(url, data = {}, config = {}) {
    return this.client.patch(url, data, config);
  }


  delete(url, config = {}) {
    return this.client.delete(url, config);
  }

}
