import ApiService from '@/admin/services/ApiService';

let instance = null;

export default class ShowPerformanceHistoryService {

  static getInstance() {
    if (!instance) {
      instance = new ShowPerformanceHistoryService();
    }

    return instance;
  }

  async getHistory(params = {}) {
    return ApiService.getInstance().get('/api/admin/show-performance-histories', { params });
  }

  async createHistory(payload) {
    return ApiService.getInstance().post('/api/admin/show-performance-histories', payload);
  }

  async updateHistory(historyId, payload) {
    return ApiService.getInstance().put(`/api/admin/show-performance-histories/${historyId}`, payload);
  }

  async deleteHistory(historyId) {
    return ApiService.getInstance().delete(`/api/admin/show-performance-histories/${historyId}`);
  }
}
