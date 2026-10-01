import ApiService from '@/admin/services/ApiService';

let instance = null;

export default class SeasonTicketTypeService {

  static getInstance() {
    if (!instance) {
      instance = new SeasonTicketTypeService();
    }
    return instance;
  }


  async getSeasonTicketTypes(params = {}) {
    const ticketTypes = await ApiService.getInstance().get('/api/admin/season-ticket-types', { params });
    return ticketTypes;
  }


  async createSeasonTicketType(payload) {
    const ticketType = await ApiService.getInstance().post('/api/admin/season-ticket-types', payload);
    return ticketType;
  }


  async updateSeasonTicketType(ticketTypeId, payload) {
    const ticketType = await ApiService.getInstance().put(`/api/admin/season-ticket-types/${ticketTypeId}`, payload);
    return ticketType;
  }


  async deleteSeasonTicketType(ticketTypeId) {
    const ticketType = await ApiService.getInstance().delete(`/api/admin/season-ticket-types/${ticketTypeId}`);
    return ticketType;
  }
}
