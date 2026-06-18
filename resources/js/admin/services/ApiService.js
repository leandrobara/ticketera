import axios from 'axios';

let instance = null;

export default class ApiService {

  constructor() {
    this.client = axios.create({
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
      },
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


  get(url, config = {}) {
    return this.client.get(url, config);
  }


  post(url, data = {}, config = {}) {
    return this.client.post(url, data, config);
  }


  put(url, data = {}, config = {}) {
    return this.client.put(url, data, config);
  }


  delete(url, config = {}) {
    return this.client.delete(url, config);
  }

}
