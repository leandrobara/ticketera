import ApiService from '@/site/services/ApiService';

let instance = null;

export default class ShowService {

  static getInstance() {
    if (!instance) {
      instance = new ShowService();
    }

    return instance;
  }

  getShow(seasonId) {
    return ApiService.getInstance().get(`/api/site/seasons/${seasonId}`);
  }

}
