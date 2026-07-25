import ApiService from '@/site/services/ApiService';

let instance = null;

export default class CommentService {

  static getInstance() {
    if (!instance) {
      instance = new CommentService();
    }

    return instance;
  }

  async getComments(seasonId, params = {}) {
    return ApiService.getInstance().get(`/api/shows/${seasonId}/comments`, { params });
  }

  async requestCommentLink(showId, email) {
    return ApiService.getInstance().post(
      `/api/site/shows/${showId}/comment-requests`,
      { email }
    );
  }

  async validateToken(token) {
    return ApiService.getInstance().get(`/api/site/comment-tokens/${token}`);
  }

  async createComment(token, payload) {
    return ApiService.getInstance().post(`/api/site/comment-tokens/${token}/comments`, payload);
  }
}
