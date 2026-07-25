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

  get(url, config = {}) {
    return this.client.get(url, config);
  }

  post(url, data = {}, config = {}) {
    return this.client.post(url, data, config);
  }

}
