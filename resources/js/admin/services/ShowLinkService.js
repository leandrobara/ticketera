import ApiService from '@/admin/services/ApiService';

let instance = null;

export default class ShowLinkService {

  static getInstance() {
    if (!instance) {
      instance = new ShowLinkService();
    }

    return instance;
  }

  async getLinks(params = {}) {
    return ApiService.getInstance().get('/api/admin/show-links', { params });
  }

  async createLink(payload) {
    return ApiService.getInstance().post('/api/admin/show-links', payload);
  }

  async updateLink(showLinkId, payload) {
    return ApiService.getInstance().put(`/api/admin/show-links/${showLinkId}`, payload);
  }

  async deleteLink(showLinkId) {
    return ApiService.getInstance().delete(`/api/admin/show-links/${showLinkId}`);
  }
}
