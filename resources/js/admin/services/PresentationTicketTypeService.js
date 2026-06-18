import ApiService from '@/admin/services/ApiService';

let instance = null;

export default class PresentationTicketTypeService {

  static getInstance() {
    if (!instance) {
      instance = new PresentationTicketTypeService();
    }
    return instance;
  }


  async getPresentationTicketTypes(params = {}) {
    const ticketTypes = await ApiService.getInstance().get('/api/admin/presentation-ticket-types', { params });
    return ticketTypes;
  }


  async createPresentationTicketType(payload) {
    const ticketType = await ApiService.getInstance().post('/api/admin/presentation-ticket-types', payload);
    return ticketType;
  }


  async updatePresentationTicketType(ticketTypeId, payload) {
    const ticketType = await ApiService.getInstance().put(`/api/admin/presentation-ticket-types/${ticketTypeId}`, payload);
    return ticketType;
  }


  async deletePresentationTicketType(ticketTypeId) {
    const ticketType = await ApiService.getInstance().delete(`/api/admin/presentation-ticket-types/${ticketTypeId}`);
    return ticketType;
  }
}
