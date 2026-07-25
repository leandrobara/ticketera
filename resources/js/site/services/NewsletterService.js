import ApiService from '@/site/services/ApiService';

let instance = null;

export default class NewsletterService {

  static getInstance() {
    if (!instance) {
      instance = new NewsletterService();
    }

    return instance;
  }

  subscribe(payload) {
    return ApiService.getInstance().post('/api/site/newsletter-subscriptions', payload);
  }

}
