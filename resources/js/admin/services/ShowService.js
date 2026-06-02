import axios from 'axios';

let instance = null;

export default class ShowService {

  static getInstance() {
    if (!instance) {
      instance = new ShowService();
    }
    return instance;
  }


  async getShows(params = {}) {
    const shows = await axios.get('/api/admin/shows', { params });
    return shows;
  }
}
