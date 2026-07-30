import ApiService from '@/site/services/ApiService';

let instance = null;

export default class VenueService {

  static getInstance() {
    if (!instance) {
      instance = new VenueService();
    }

    return instance;
  }

  getVenueBySeason(seasonId) {
    return ApiService.getInstance().get(`/api/site/season/${seasonId}/venue`);
  }

}
