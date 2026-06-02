import axios from 'axios';

let instance = null;

export default class LoginService {

  static getInstance() {
    if (!instance) {
      instance = new LoginService();
    }
    return instance;
  }


  async login({ email, password }) {

    const response = await axios.post('/api/admin/auth/login', {
      email: email,
      password: password,
    });

    return response;
  }


  async me() {
    const user = await axios.get('/api/admin/auth/me');
    return user;
  }


  async logout() {
    const loggedOut = await axios.post('/api/admin/auth/logout');
    return loggedOut;
  }

}
