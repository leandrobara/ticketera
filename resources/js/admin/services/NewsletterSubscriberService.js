import ApiService from '@/admin/services/ApiService';

let instance = null;

export default class NewsletterSubscriberService {

  static getInstance() {
    if (!instance) {
      instance = new NewsletterSubscriberService();
    }
    return instance;
  }


  async getSubscribers(params = {}) {
    const subscribers = await ApiService.getInstance().get('/api/admin/newsletter-subscribers', { params });
    return subscribers;
  }


  async deleteSubscriber(subscriberId) {
    const subscriber = await ApiService.getInstance().delete(`/api/admin/newsletter-subscribers/${subscriberId}`);
    return subscriber;
  }
}
