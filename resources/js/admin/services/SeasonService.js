import ApiService from '@/admin/services/ApiService';

let instance = null;

export default class SeasonService {

  static getInstance() {
    if (!instance) {
      instance = new SeasonService();
    }

    return instance;
  }

  getSeasons(params = {}) {
    return ApiService.getInstance().get('/api/admin/seasons', { params });
  }

  getSeason(id) {
    return ApiService.getInstance().get(`/api/admin/seasons/${id}`);
  }

  createSeason(payload) {
    return ApiService.getInstance().post('/api/admin/seasons', payload);
  }

  updateSeason(id, payload) {
    return ApiService.getInstance().put(`/api/admin/seasons/${id}`, payload);
  }

  deleteSeason(id) {
    return ApiService.getInstance().delete(`/api/admin/seasons/${id}`);
  }

}
