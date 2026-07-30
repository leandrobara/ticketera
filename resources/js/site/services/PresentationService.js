import ApiService from '@/site/services/ApiService';

let instance = null;

export default class PresentationService {

  static getInstance() {
    if (!instance) {
      instance = new PresentationService();
    }

    return instance;
  }

  getPresentations(seasonId, promoCode = null) {
    const config = promoCode
      ? { params: { promo_code: promoCode } }
      : {};

    return ApiService.getInstance().get(`/api/site/season/${seasonId}/presentations`, config);
  }

}
