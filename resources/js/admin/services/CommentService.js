import ApiService from '@/admin/services/ApiService';

let instance = null;

export default class CommentService {

  static getInstance() {
    if (!instance) {
      instance = new CommentService();
    }

    return instance;
  }

  async getComments(params = {}) {
    return ApiService.getInstance().get('/api/admin/comments', { params });
  }

  async updateComment(commentId, payload) {
    return ApiService.getInstance().put(`/api/admin/comments/${commentId}`, payload);
  }

  async deleteComment(commentId) {
    return ApiService.getInstance().delete(`/api/admin/comments/${commentId}`);
  }
}
