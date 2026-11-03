/**
 * httpRequest.ts
 *
 * Builder action definition for the "HTTP request" action, which calls any API
 * and keeps its response on the payload under a name, for the later actions to
 * read through {{ http:NAME.path }}. Provides data normalization, the canvas
 * description, and validation. The runtime lives in
 * admin/src/Builder/Http_Request_Action.php.
 *
 * @since 2.7.0
 */
import HttpRequestSettings from '../settings/HttpRequestSettings.vue';
import { normalizeValidationErrors, requiredFieldErrors } from '../utils/validators';
import type { ActionDefinition } from '../registry/types';
import { HTTP_REQUEST_ICON } from './actionIcons';
import { __, textDomain } from '../../../utils/i18n';

export interface HttpRequestRow {
  key: string;
  value: string;
}

export const HTTP_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

// Prefix of a credential sealed by the server's Token_Vault.
export const SEALED_PREFIX = 'enc:v1:';

/**
 * Normalizes a list field into { key, value } rows, dropping anything else.
 *
 * @since 2.7.0
 * @param {unknown} value Raw rows.
 * @returns {HttpRequestRow[]} The rows.
 */
export function normalizeRows(value: unknown): HttpRequestRow[] {
  const source = Array.isArray(value) ? value : value && typeof value === 'object' ? Object.values(value) : [];

  return source
    .filter((row): row is Record<string, unknown> => Boolean(row) && typeof row === 'object')
    .map((row) => ({ key: String(row.key ?? ''), value: String(row.value ?? '') }));
}

/**
 * Turns a typed name into the response variable slug the server keeps, the
 * same way sanitize_key() does: lowercase letters, numbers, - and _.
 *
 * @since 2.7.0
 * @param {unknown} value Typed name.
 * @returns {string} The slug.
 */
export function responseVarSlug(value: unknown): string {
  return String(value ?? '').toLowerCase().replace(/[^a-z0-9_-]/g, '');
}

/**
 * Normalizes/serializes the HTTP request action payload, applying defaults.
 *
 * @since 2.7.0
 * @param {Record<string, unknown>} data Raw action data.
 * @returns {Record<string, unknown>} Normalized HTTP request action data.
 */
function normalizeHttpRequestData(data: Record<string, unknown>): Record<string, unknown> {
  const method = String(data.method || 'GET').toUpperCase();

  return {
    title: String(data.title || __('HTTP request', textDomain)),
    description: String(data.description || ''),
    action: 'http_request',
    method: HTTP_METHODS.includes(method) ? method : 'GET',
    url: String(data.url || ''),
    query: normalizeRows(data.query),
    headers: normalizeRows(data.headers),
    auth_type: String(data.auth_type || 'none'),
    auth_user: String(data.auth_user || ''),
    auth_header: String(data.auth_header ?? 'X-API-Key'),
    auth_value: String(data.auth_value || ''),
    body_mode: String(data.body_mode || 'none'),
    body: String(data.body || ''),
    body_fields: normalizeRows(data.body_fields),
    content_type: String(data.content_type || 'text/plain'),
    timeout: String(data.timeout ?? '10'),
    response_var: responseVarSlug(data.response_var),
    on_error: data.on_error === 'stop' ? 'stop' : 'continue',
  };
}

/**
 * Builds the canvas line: method, host and the response token.
 *
 * @since 2.7.0
 * @param {Record<string, unknown>} data Action data.
 * @returns {string} The description.
 */
function describeHttpRequest(data: Record<string, unknown>): string {
  let host = '';

  try {
    host = new URL(String(data.url || '')).host;
  } catch {
    host = '';
  }

  if (!host) {
    return __('Set the URL', textDomain);
  }

  const name = responseVarSlug(data.response_var);
  const line = `${String(data.method || 'GET').toUpperCase()} ${host}`;

  return name ? `${line} → {{ http:${name} }}` : line;
}

/**
 * Validates the URL, the JSON body when one is expected and the credential.
 *
 * @since 2.7.0
 * @param {Record<string, unknown>} data Action data.
 * @returns {Record<string, string>} Validation errors.
 */
function validateHttpRequest(data: Record<string, unknown>) {
  const errors = requiredFieldErrors(data, [{ key: 'url', label: __('URL', textDomain) }]);
  const method = String(data.method || 'GET').toUpperCase();

  if (method !== 'GET' && data.body_mode === 'json' && !String(data.body || '').trim()) {
    errors.body = __('Type the JSON to send.', textDomain);
  }

  if (data.auth_type && data.auth_type !== 'none' && !String(data.auth_value || '').trim()) {
    errors.auth_value = __('Type the credential, or choose no authentication.', textDomain);
  }

  return normalizeValidationErrors(errors);
}

export const httpRequestDefinition: ActionDefinition = {
  action: 'http_request',
  title: __('HTTP request', textDomain),
  description: __('Call any API and use its response in the next actions.', textDomain),
  icon: 'globe',
  iconSvg: HTTP_REQUEST_ICON,
  category: 'general',
  hasSettings: true,
  priority: 79,
  isExpansible: true,
  defaultData: normalizeHttpRequestData({}),
  settingsComponent: HttpRequestSettings,
  normalizeData: normalizeHttpRequestData,
  serializeData: normalizeHttpRequestData,
  buildDescription: describeHttpRequest,
  validate: validateHttpRequest,
};
