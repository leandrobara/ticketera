import ApiService from '@/admin/services/ApiService';

let instance = null;

export default class UserService {

  static getInstance() {
    if (!instance) {
      instance = new UserService();
    }
    return instance;
  }


  async getUsers(params = {}) {
    return ApiService.getInstance().get('/api/admin/users', { params });
  }


  async createUser(payload) {
    return ApiService.getInstance().post('/api/admin/users', payload);
  }


  async updateUser(userId, payload) {
    return ApiService.getInstance().put(`/api/admin/users/${userId}`, payload);
  }


  async deleteUser(userId) {
    return ApiService.getInstance().delete(`/api/admin/users/${userId}`);
  }
}
