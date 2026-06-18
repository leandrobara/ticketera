import ApiService from '@/admin/services/ApiService';

let instance = null;

export default class OrderService {

  static getInstance() {
    if (!instance) {
      instance = new OrderService();
    }
    return instance;
  }


  async getOrders(params = {}) {
    const orders = await ApiService.getInstance().get('/api/admin/orders', { params });
    return orders;
  }


  async createOrder(payload) {
    const order = await ApiService.getInstance().post('/api/admin/orders', payload);
    return order;
  }


  async cancelOrder(orderId) {
    const order = await ApiService.getInstance().post(`/api/admin/orders/${orderId}/cancel`);
    return order;
  }
}
