import ApiService from '@/admin/services/ApiService';

let instance = null;

export default class LoginService {

  static getInstance() {
    if (!instance) {
      instance = new LoginService();
    }
    return instance;
  }


  async login({ email, password }) {

    const response = await ApiService.getInstance().post('/api/admin/auth/login', {
      email: email,
      password: password,
    });

    return response;
  }


  async me() {
    const user = await ApiService.getInstance().get('/api/admin/auth/me');
    return user;
  }


  async logout() {
    const loggedOut = await ApiService.getInstance().post('/api/admin/auth/logout');
    return loggedOut;
  }

}
