import ApiService from '@/site/services/ApiService';

let instance = null;

export default class CheckoutService {

  static getInstance() {
    if (!instance) {
      instance = new CheckoutService();
    }

    return instance;
  }

  createOrder(payload) {
    return ApiService.getInstance().post('/api/checkout/create-order', payload);
  }

  pricePreview(payload) {
    return ApiService.getInstance().post('/api/checkout/price-preview', payload);
  }

}
