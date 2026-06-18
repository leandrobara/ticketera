import ApiService from '@/admin/services/ApiService';

let instance = null;

export default class BuyerService {

  static getInstance() {
    if (!instance) {
      instance = new BuyerService();
    }
    return instance;
  }


  async getBuyers(params = {}) {
    const buyers = await ApiService.getInstance().get('/api/admin/buyers', { params });
    return buyers;
  }


  async createBuyer(payload) {
    const buyer = await ApiService.getInstance().post('/api/admin/buyers', payload);
    return buyer;
  }


  async updateBuyer(buyerId, payload) {
    const buyer = await ApiService.getInstance().put(`/api/admin/buyers/${buyerId}`, payload);
    return buyer;
  }


  async deleteBuyer(buyerId) {
    const buyer = await ApiService.getInstance().delete(`/api/admin/buyers/${buyerId}`);
    return buyer;
  }
}
