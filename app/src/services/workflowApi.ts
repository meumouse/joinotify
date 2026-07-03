/**
 * workflowApi.ts
 *
 * Factory for the workflow builder REST client. Wraps the shared API client and
 * exposes typed helper methods for every builder-related endpoint (bootstrap,
 * actions, workflows, templates, import/export, status, settings, and AI
 * generation).
 *
 * @since 2.0.0
 */
import { createApiClient } from '../utils/api';

/**
 * Creates a REST client bound to the builder endpoints.
 *
 * @since 2.0.0
 * @param {Object} bootstrap Bootstrap payload used to configure the API client.
 * @returns {Object} An object of endpoint helper methods.
 */
export function createWorkflowApiClient(bootstrap) {
  const api = createApiClient(bootstrap);

  return {
    /**
     * Loads the builder bootstrap payload for a workflow.
     *
     * @since 2.0.0
     * @param {number|string} postId The workflow post ID.
     * @returns {Promise<Object>} The bootstrap response.
     */
    loadBootstrap(postId) {
      return api.get(`/admin/builder?id=${postId || 0}`);
    },
    /**
     * Loads the available actions, optionally filtered by context.
     *
     * @since 2.0.0
     * @param {string} [context] The context filter.
     * @returns {Promise<Object>} The actions response.
     */
    loadActions(context = '') {
      const query = context ? `?context=${encodeURIComponent(context)}` : '';
      return api.get(`/admin/builder/actions${query}`);
    },
    /**
     * Loads a single action definition.
     *
     * @since 2.0.0
     * @param {string} action The action ID.
     * @returns {Promise<Object>} The action response.
     */
    loadAction(action) {
      return api.get(`/admin/builder/actions?action=${encodeURIComponent(action || '')}`);
    },
    /**
     * Loads a workflow by post ID.
     *
     * @since 2.0.0
     * @param {number|string} postId The workflow post ID.
     * @returns {Promise<Object>} The workflow response.
     */
    loadWorkflow(postId) {
      return api.get(`/admin/builder/workflow?id=${postId}`);
    },
    /**
     * Loads the available workflow templates.
     *
     * @since 2.0.0
     * @returns {Promise<Object>} The templates response.
     */
    loadTemplates() {
      return api.get('/admin/builder/templates');
    },
    /**
     * Creates a new workflow.
     *
     * @since 2.0.0
     * @param {Object} body The creation payload.
     * @returns {Promise<Object>} The creation response.
     */
    createWorkflow(body) {
      return api.post('/admin/builder/create', body);
    },
    /**
     * Saves a workflow.
     *
     * @since 2.0.0
     * @param {Object} body The workflow payload.
     * @returns {Promise<Object>} The save response.
     */
    saveWorkflow(body) {
      return api.post('/admin/builder/workflow', body);
    },
    /**
     * Saves plugin settings.
     *
     * @since 2.0.0
     * @param {Object} body The settings payload.
     * @returns {Promise<Object>} The save response.
     */
    saveSettings(body) {
      return api.post('/admin/settings', body);
    },
    /**
     * Updates a workflow's publication status.
     *
     * @since 2.0.0
     * @param {Object} body The status payload.
     * @returns {Promise<Object>} The update response.
     */
    updateWorkflowStatus(body) {
      return api.post('/admin/builder/status', body);
    },
    /**
     * Imports a workflow from a file payload.
     *
     * @since 2.0.0
     * @param {Object} body The import payload.
     * @returns {Promise<Object>} The import response.
     */
    importWorkflow(body) {
      return api.post('/admin/builder/import', body);
    },
    /**
     * Exports a workflow by post ID.
     *
     * @since 2.0.0
     * @param {number|string} postId The workflow post ID.
     * @returns {Promise<Object>} The export response.
     */
    exportWorkflow(postId) {
      return api.get(`/admin/builder/export?id=${postId}`);
    },
    /**
     * Runs a test execution of a workflow.
     *
     * @since 2.0.0
     * @param {Object} body The test payload.
     * @returns {Promise<Object>} The test response.
     */
    runWorkflowTest(body) {
      return api.post('/admin/builder/test', body);
    },
    /**
     * Generates a workflow using AI.
     *
     * @since 2.0.0
     * @param {Object} body The generation payload.
     * @returns {Promise<Object>} The generation response.
     */
    generateAiWorkflow(body) {
      return api.post('/admin/ai/generate', body);
    },
    /**
     * Generates content using AI.
     *
     * @since 2.0.0
     * @param {Object} body The generation payload.
     * @returns {Promise<Object>} The generation response.
     */
    generateAi(body) {
      return api.post('/admin/ai/generate', body);
    },
  };
}
