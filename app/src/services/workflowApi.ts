import { createApiClient } from '../utils/api';

export function createWorkflowApiClient(bootstrap) {
  const api = createApiClient(bootstrap);

  return {
    loadBootstrap(postId) {
      return api.get(`/admin/builder?id=${postId || 0}`);
    },
    loadActions(context = '') {
      const query = context ? `?context=${encodeURIComponent(context)}` : '';
      return api.get(`/admin/builder/actions${query}`);
    },
    loadAction(action) {
      return api.get(`/admin/builder/actions?action=${encodeURIComponent(action || '')}`);
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
    saveSettings(body) {
      return api.post('/admin/settings', body);
    },
    updateWorkflowStatus(body) {
      return api.post('/admin/builder/status', body);
    },
    importWorkflow(body) {
      return api.post('/admin/builder/import', body);
    },
    exportWorkflow(postId) {
      return api.get(`/admin/builder/export?id=${postId}`);
    },
    runWorkflowTest(body) {
      return api.post('/admin/builder/test', body);
    },
    generateAiWorkflow(body) {
      return api.post('/admin/ai/generate', body);
    },
  };
}
