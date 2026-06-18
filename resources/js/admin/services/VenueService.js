import ApiService from '@/admin/services/ApiService';

let instance = null;

export default class VenueService {

  static getInstance() {
    if (!instance) {
      instance = new VenueService();
    }
    return instance;
  }


  async getVenues(params = {}) {
    const venues = await ApiService.getInstance().get('/api/admin/venues', { params });
    return venues;
  }


  async createVenue(payload) {
    const venue = await ApiService.getInstance().post('/api/admin/venues', payload);
    return venue;
  }


  async updateVenue(venueId, payload) {
    const venue = await ApiService.getInstance().put(`/api/admin/venues/${venueId}`, payload);
    return venue;
  }


  async deleteVenue(venueId) {
    const venue = await ApiService.getInstance().delete(`/api/admin/venues/${venueId}`);
    return venue;
  }
}
