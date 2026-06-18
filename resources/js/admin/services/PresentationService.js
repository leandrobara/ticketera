import ApiService from '@/admin/services/ApiService';

let instance = null;

export default class PresentationService {

  static getInstance() {
    if (!instance) {
      instance = new PresentationService();
    }
    return instance;
  }


  async getPresentations(params = {}) {
    const presentations = await ApiService.getInstance().get('/api/admin/presentations', { params });
    return presentations;
  }


  async createPresentation(payload) {
    const presentation = await ApiService.getInstance().post('/api/admin/presentations', payload);
    return presentation;
  }


  async updatePresentation(presentationId, payload) {
    const presentation = await ApiService.getInstance().put(`/api/admin/presentations/${presentationId}`, payload);
    return presentation;
  }


  async deletePresentation(presentationId) {
    const presentation = await ApiService.getInstance().delete(`/api/admin/presentations/${presentationId}`);
    return presentation;
  }
}
