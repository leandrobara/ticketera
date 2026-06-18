import ApiService from '@/admin/services/ApiService';

let instance = null;

export default class OrderItemService {

  static getInstance() {
    if (!instance) {
      instance = new OrderItemService();
    }
    return instance;
  }


  async calculateAmounts(payload) {
    const response = await ApiService.getInstance().post('/api/checkout/price-preview', payload);
    return response;
  }
}
