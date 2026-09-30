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

    /**
     * Loads the custom fields and tags of the account.
     *
     * @since 2.5.0
     * @param {boolean} [refresh] Skip the server cache.
     * @param {boolean} [archived] Include archived fields.
     * @returns {Promise<{fields: Array, tags: Array}>} The definitions.
     */
    definitions(refresh = false, archived = false) {
      return get('/admin/contacts/definitions', { refresh: refresh ? 1 : '', archived: archived ? 1 : '' });
    },

    /**
     * Lists contacts.
     *
     * @since 2.5.0
     * @param {Object} params `page`, `per_page`, `sort`, `search`, `tag_id`, `opt_in_status`, `source`, `audience_id`.
     * @returns {Promise<{items: Array, pagination: Object}>} One page of contacts.
     */
    listContacts(params: Record<string, unknown>) {
      return get('/admin/contacts', params);
    },

    /**
     * Loads one contact and the site's users that are the same person.
     *
     * @since 2.5.0
     * @param {string} id Contact id.
     * @returns {Promise<{contact: Object, wp_users: Array}>} The contact.
     */
    contact(id: string) {
      return get('/admin/contacts/detail', { id });
    },

    /**
     * Loads a contact's history.
     *
     * @since 2.5.0
     * @param {string} id Contact id.
     * @param {number} [page] Page.
     * @returns {Promise<{items: Array, pagination: Object}>} One page of activity.
     */
    activity(id: string, page = 1) {
      return get('/admin/contacts/activity', { id, page, per_page: 20 });
    },

    /**
     * Creates a contact, or changes one when `id` is given.
     *
     * @since 2.5.0
     * @param {Object} payload `{ id?, contact, upsert? }`.
     * @returns {Promise<{message: string, contact: Object}>} The saved contact.
     */
    saveContact(payload: Record<string, unknown>) {
      return post('/admin/contacts/save', payload);
    },

    /**
     * Erases a contact for good.
     *
     * @since 2.5.0
     * @param {string} id Contact id.
     * @returns {Promise<{message: string}>} The result.
     */
    deleteContact(id: string) {
      return post('/admin/contacts/delete', { id });
    },

    /**
     * Records an opt-in (with evidence) or an opt-out (with an optional reason).
     *
     * @since 2.5.0
     * @param {string} id Contact id.
     * @param {'opt_in'|'opt_out'} action Consent action.
     * @param {string} text Evidence or reason.
     * @returns {Promise<{message: string, contact: Object}>} The updated contact.
     */
    consent(id: string, action: 'opt_in' | 'opt_out', text: string) {
      return post('/admin/contacts/consent', action === 'opt_in' ? { id, action, evidence: text } : { id, action, reason: text });
    },

    /**
     * Exports the contacts the filters select as CSV.
     *
     * @since 2.5.0
     * @param {Object} filters Listing filters.
     * @returns {Promise<{filename: string, content: string}>} The file.
     */
    exportContacts(filters: Record<string, unknown>) {
      return get('/admin/contacts/export', filters);
    },

    /**
     * Exports everything the account keeps about one contact as JSON.
     *
     * @since 2.5.0
     * @param {string} id Contact id.
     * @returns {Promise<{filename: string, content: Object}>} The file.
     */
    exportContact(id: string) {
      return get('/admin/contacts/export', { id });
    },

    /**
     * Creates a custom field, or changes one when `id` is given.
     *
     * @since 2.5.0
     * @param {string} id Field id, or an empty string to create.
     * @param {Object} field Field values.
     * @returns {Promise<{message: string, field: Object}>} The saved field.
     */
    saveField(id: string, field: Record<string, unknown>) {
      return post('/admin/contacts/fields/save', { id, field });
    },

    /**
     * Erases a custom field and its value on every contact.
     *
     * @since 2.5.0
     * @param {string} id Field id.
     * @returns {Promise<{message: string}>} The result.
     */
    deleteField(id: string) {
      return post('/admin/contacts/fields/delete', { id });
    },

    /**
     * Creates a tag, or changes one when `id` is given.
     *
     * @since 2.5.0
     * @param {string} id Tag id, or an empty string to create.
     * @param {Object} tag `{ name, description, color }`.
     * @returns {Promise<{message: string, tag: Object}>} The saved tag.
     */
    saveTag(id: string, tag: Record<string, unknown>) {
      return post('/admin/contacts/tags/save', { id, tag });
    },

    /**
     * Erases a tag and takes it off every contact.
     *
     * @since 2.5.0
     * @param {string} id Tag id.
     * @returns {Promise<{message: string}>} The result.
     */
    deleteTag(id: string) {
      return post('/admin/contacts/tags/delete', { id });
    },

    /**
     * Puts a tag on (or takes it off) the listed contacts, or every contact the filters select.
     *
     * @since 2.5.0
     * @param {Object} payload `{ tag_id, action, contact_ids?, filters? }`.
     * @returns {Promise<{message: string, affected: number}>} How many contacts changed.
     */
    applyTag(payload: Record<string, unknown>) {
      return post('/admin/contacts/tags/apply', payload);
    },
  };
}

export type ContactsApiClient = ReturnType<typeof createContactsApiClient>;
