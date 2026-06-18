import ApiService from '@/admin/services/ApiService';

let instance = null;

export default class PromotionService {

  static getInstance() {
    if (!instance) {
      instance = new PromotionService();
    }
    return instance;
  }


  async getPromotions(params = {}) {
    const promotions = await ApiService.getInstance().get('/api/admin/promotions', { params });
    return promotions;
  }


  async createPromotion(payload) {
    const promotion = await ApiService.getInstance().post('/api/admin/promotions', payload);
    return promotion;
  }


  async updatePromotion(promotionId, payload) {
    const promotion = await ApiService.getInstance().put(`/api/admin/promotions/${promotionId}`, payload);
    return promotion;
  }


  async deletePromotion(promotionId) {
    const promotion = await ApiService.getInstance().delete(`/api/admin/promotions/${promotionId}`);
    return promotion;
  }
}
