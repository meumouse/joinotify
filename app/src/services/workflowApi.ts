import { createApiClient } from '../utils/api';

export function createWorkflowApiClient(bootstrap) {
  const api = createApiClient(bootstrap);

  return {
    loadBootstrap(postId) {
      return api.get(`/admin/builder?id=${postId || 0}`);
    },
    loadWorkflow(postId) {
      return api.get(`/admin/builder/workflow?id=${postId}`);
    },
    loadTemplates() {
      return api.get('/admin/builder/templates');
    },
    createWorkflow(body) {
      return api.post('/admin/builder/create', body);
    },
    saveWorkflow(body) {
      return api.post('/admin/builder/workflow', body);
    },
    importWorkflow(body) {
      return api.post('/admin/builder/import', body);
    },
    exportWorkflow(postId) {
      return api.get(`/admin/builder/export?id=${postId}`);
    },
  };
}
