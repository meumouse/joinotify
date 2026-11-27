/**
 * useWhatsappTemplates.ts
 *
 * Loads the Meta-approved WhatsApp message templates of the connected account
 * and asks the API to sync them with Meta. Shared by the builder's template
 * action and the test message of the settings.
 *
 * @since 2.8.0
 */
import { computed, ref } from 'vue';
import { __, textDomain } from '../utils/i18n';
import type { RemoteTemplate } from '../utils/whatsappTemplates';

// utils/api.js types its helpers as bare `Function`, so the client is only
// asked to be callable.
interface ApiClient {
  get: Function;
  post: Function;
}

interface Options {
  /** Keep only APPROVED templates, the ones Meta delivers. */
  approvedOnly?: boolean;
  /**
   * Business account to list. A template only goes out from a number of the
   * account that owns it, so a caller that knows the sender asks for its
   * account; without it the API answers for the default one.
   */
  wabaId?: () => string;
}

/**
 * Template list of the account, with loading and sync state.
 *
 * @since 2.8.0
 * @param {ApiClient|null} api REST client of the current app.
 * @param {Options} [options] List options.
 * @returns The list, its state and the actions that fill it.
 */
export function useWhatsappTemplates(api: ApiClient | null, options: Options = {}) {
  const allTemplates = ref<RemoteTemplate[]>([]);
  const loading = ref(false);
  const syncing = ref(false);
  const loaded = ref(false);
  const errorMsg = ref('');
  const staleWarning = ref('');

  const templates = computed(() =>
    options.approvedOnly
      ? allTemplates.value.filter((template) => String(template.status || '').toUpperCase() === 'APPROVED')
      : allTemplates.value
  );

  /**
   * Load the templates of the account.
   *
   * @since 2.3.0
   * @version 2.8.0
   * @param {boolean} [force] Ask the API to reconcile its mirror with Meta first.
   * @returns {Promise<void>}
   */
  async function loadTemplates(force = false): Promise<void> {
    if (!api) {
      return;
    }

    loading.value = true;
    errorMsg.value = '';
    staleWarning.value = '';

    const query = new URLSearchParams();
    const wabaId = options.wabaId ? String(options.wabaId() || '') : '';

    if (force) {
      query.set('refresh', 'true');
    }

    if (wabaId) {
      query.set('waba_id', wabaId);
    }

    try {
      const res = await api.get(`admin/builder/whatsapp-templates${query.toString() ? `?${query.toString()}` : ''}`);

      if (res?.status === 'error') {
        errorMsg.value = res.message || __('Could not load your message templates.', textDomain);
        return;
      }

      allTemplates.value = Array.isArray(res?.templates) ? res.templates : [];

      if (res?.stale) {
        staleWarning.value = res.sync_error || __('This list may be out of date.', textDomain);
      }
    } catch (error) {
      errorMsg.value = error instanceof Error ? error.message : String(error);
    } finally {
      loading.value = false;
      loaded.value = true;
    }
  }

  /**
   * Ask the API to reconcile its template mirror with Meta, then reload.
   *
   * @since 2.3.0
   * @version 2.8.0
   * @returns {Promise<void>}
   */
  async function syncTemplates(): Promise<void> {
    if (!api) {
      return;
    }

    syncing.value = true;
    errorMsg.value = '';

    try {
      const res = await api.post('admin/builder/whatsapp-templates/sync', {});

      if (res?.status === 'error') {
        errorMsg.value = res.message || __('Could not sync your message templates.', textDomain);
        return;
      }

      // The sync answers with the default account's list; another account is
      // read again now that the cache was cleared.
      if (options.wabaId && options.wabaId()) {
        await loadTemplates();
        return;
      }

      allTemplates.value = Array.isArray(res?.templates) ? res.templates : [];
    } catch (error) {
      errorMsg.value = error instanceof Error ? error.message : String(error);
    } finally {
      syncing.value = false;
    }
  }

  return { templates, allTemplates, loading, syncing, loaded, errorMsg, staleWarning, loadTemplates, syncTemplates };
}
