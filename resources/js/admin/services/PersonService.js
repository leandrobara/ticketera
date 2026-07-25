import ApiService from '@/admin/services/ApiService';

let instance = null;

export default class PersonService {

  static getInstance() {
    if (!instance) {
      instance = new PersonService();
    }
    return instance;
  }

  async getPeople(params = {}) {
    const people = await ApiService.getInstance().get('/api/admin/people', { params });
    return people;
  }

  async getPersonCandidates(params = {}) {
    const people = await ApiService.getInstance().get('/api/admin/people/candidates', { params });
    return people;
  }

  async createPerson(payload) {
    const person = await ApiService.getInstance().post('/api/admin/people', payload);
    return person;
  }

  async updatePerson(personId, payload) {
    const person = await ApiService.getInstance().put(`/api/admin/people/${personId}`, payload);
    return person;
  }

  async deletePerson(personId) {
    const person = await ApiService.getInstance().delete(`/api/admin/people/${personId}`);
    return person;
  }
}
