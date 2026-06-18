import ApiService from '@/admin/services/ApiService';

let instance = null;

export default class TicketService {

  static getInstance() {
    if (!instance) {
      instance = new TicketService();
    }
    return instance;
  }

  async getTickets(params = {}) {
    const tickets = await ApiService.getInstance().get('/api/admin/tickets', { params });
    return tickets;
  }


  async cancelTicket(ticketId) {
    const ticket = await ApiService.getInstance().post(`/api/admin/tickets/${ticketId}/cancel`);
    return ticket;
  }

  async markTicketUsed(ticketId) {
    const ticket = await ApiService.getInstance().post(`/api/admin/tickets/${ticketId}/mark-used`);
    return ticket;
  }
}
