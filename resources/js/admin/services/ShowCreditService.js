import ApiService from '@/admin/services/ApiService';

let instance = null;

export default class ShowCreditService {

  static getInstance() {
    if (!instance) {
      instance = new ShowCreditService();
    }
    return instance;
  }

  async getShowCredits(params = {}) {
    const showCredits = await ApiService.getInstance().get('/api/admin/show-credits', { params });
    return showCredits;
  }

  async createShowCredit(payload) {
    const showCredit = await ApiService.getInstance().post('/api/admin/show-credits', payload);
    return showCredit;
  }

  async updateShowCredit(showCreditId, payload) {
    const method = payload instanceof FormData ? 'post' : 'put';
    const showCredit = await ApiService.getInstance()[method](`/api/admin/show-credits/${showCreditId}`, payload);
    return showCredit;
  }

  async deleteShowCredit(showCreditId) {
    const showCredit = await ApiService.getInstance().delete(`/api/admin/show-credits/${showCreditId}`);
    return showCredit;
  }
}
