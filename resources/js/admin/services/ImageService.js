import ApiService from '@/admin/services/ApiService';

let instance = null;

export default class ImageService {

  static getInstance() {
    if (!instance) {
      instance = new ImageService();
    }
    return instance;
  }


  async getImages(params = {}) {
    const images = await ApiService.getInstance().get('/api/admin/images', { params });
    return images;
  }


  async createImage(payload) {
    const image = await ApiService.getInstance().post('/api/admin/images', payload, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return image;
  }


  async updateImage(imageId, payload) {
    const image = await ApiService.getInstance().post(`/api/admin/images/${imageId}`, payload, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return image;
  }


  async deleteImage(imageId) {
    const image = await ApiService.getInstance().delete(`/api/admin/images/${imageId}`);
    return image;
  }
}
