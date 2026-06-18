import ApiService from '@/admin/services/ApiService';

let instance = null;

export default class ShowService {

  static getInstance() {
    if (!instance) {
      instance = new ShowService();
    }
    return instance;
  }


  async getShows(params = {}) {
    const shows = await ApiService.getInstance().get('/api/admin/shows', { params });
    return shows;
  }


  async createShow(payload) {
    const show = await ApiService.getInstance().post('/api/admin/shows', payload);
    return show;
  }


  async updateShow(showId, payload) {
    const show = await ApiService.getInstance().put(`/api/admin/shows/${showId}`, payload);
    return show;
  }


  async deleteShow(showId) {
    const show = await ApiService.getInstance().delete(`/api/admin/shows/${showId}`);
    return show;
  }
}
