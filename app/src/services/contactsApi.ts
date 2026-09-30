/**
 * contactsApi.ts
 *
 * REST client of the "Audiences & Contacts" screen. Every call goes to the
 * plugin's own `joinotify/v1/admin/...` routes, which relay to the Joinotify
 * platform with the site's API key; the key never reaches the browser.
 *
 * The routes answer HTTP 200 with `status: 'error'` when the platform refused
 * something. This client turns those answers into a `CloudRequestError` that
 * carries the platform's stable `type`, the per-field `issues`, the
 * `retryAfter` of a rate limit and the panel `actionUrl` of a billing block.
 *
 * @since 2.5.0
 */
import { createApiClient } from '../utils/api';
import { __, textDomain } from '../utils/i18n';

export interface CloudIssue {
  field: string;
  message: string;
}

export interface CloudErrorDetails {
  type?: string;
  message?: string;
  issues?: CloudIssue[];
  retry_after?: number;
  action_url?: string;
  contact_id?: string;
  reason?: string;
}

/**
 * Error raised when the platform (or the relay route) refused a request.
 *
 * @since 2.5.0
 */
export class CloudRequestError extends Error {
  type: string;
  issues: CloudIssue[];
  retryAfter: number;
  actionUrl: string;
  contactId: string;

  constructor(message: string, details: CloudErrorDetails = {}) {
    super(message || details.message || __('Request failed.', textDomain));
    this.name = 'CloudRequestError';
    this.type = details.type || 'unknown_error';
    this.issues = Array.isArray(details.issues) ? details.issues : [];
    this.retryAfter = Number(details.retry_after) || 0;
    this.actionUrl = details.action_url || '';
    this.contactId = details.contact_id || '';
  }

  /**
   * Message of the issue reported for a field, e.g. `phone` or `attributes.city`.
   *
   * @since 2.5.0
   * @param {string} field Dotted field path.
   * @returns {string} The message, or an empty string.
   */
  issueFor(field: string): string {
    const issue = this.issues.find((item) => item.field === field || item.field.endsWith(`.${field}`));

    return issue ? issue.message : '';
  }
}

/**
 * Builds a query string from the params that carry a value.
 *
 * @since 2.5.0
 * @param {Record<string, unknown>} params Query params.
 * @returns {string} The query string, with its leading `?`, or an empty string.
 */
export function toQuery(params: Record<string, unknown> = {}): string {
  const query = new URLSearchParams();

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '' || value === false) {
      return;
    }

    query.set(key, String(value));
  });

  const built = query.toString();

  return built ? `?${built}` : '';
}

/**
 * Creates the REST client of the contacts screen.
 *
 * @since 2.5.0
 * @param {Object} bootstrap Bootstrap payload holding `rest.root` and `rest.nonce`.
 * @returns {Object} Endpoint helpers; each resolves with the route's body or throws a CloudRequestError.
 */
export function createContactsApiClient(bootstrap: any) {
  const api = createApiClient(bootstrap);

  async function unwrap(promise: Promise<any>): Promise<any> {
    let response;

    try {
      response = await promise;
    } catch (error: any) {
      throw new CloudRequestError(error?.message || __('Request failed.', textDomain), { type: 'http_error' });
    }

    if (response?.status === 'error') {
      throw new CloudRequestError(response.message, response.error || {});
    }

    return response;
  }

  function get(path: string, params: Record<string, unknown> = {}) {
    return unwrap(api.get(`${path}${toQuery(params)}`));
  }

  function post(path: string, body: Record<string, unknown> = {}) {
    return unwrap(api.post(path, body));
  }

  return {
    get,
    post,

    /**
     * Reloads the screen's bootstrap, asking the platform again what the key may do.
     *
     * @since 2.5.0
     * @returns {Promise<Object>} The bootstrap payload.
     */
    bootstrap() {
      return api.get('/admin/contacts/bootstrap?refresh=1');
    },
  };
}

export type ContactsApiClient = ReturnType<typeof createContactsApiClient>;
