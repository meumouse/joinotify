/**
 * context.ts
 *
 * What the tabs of the "Audiences & Contacts" screen share: the REST client,
 * the bootstrap, whether the key may change the contact base, the toast queue
 * and the navigation between tabs (e.g. "see the contacts of this audience").
 *
 * @since 2.5.0
 */
import { inject, type ComputedRef, type InjectionKey } from 'vue';
import { __, sprintf, textDomain } from '../../utils/i18n';
import { CloudRequestError, type ContactsApiClient } from '../../services/contactsApi';

export interface ContactsContext {
  api: ContactsApiClient;
  bootstrap: Record<string, any>;
  canWrite: ComputedRef<boolean>;
  toast: (message: string, tone?: string, title?: string) => void;
  notifyError: (error: unknown, fallback?: string) => void;
  navigate: (tab: string, params?: Record<string, string>) => void;
  route: ComputedRef<{ tab: string; params: Record<string, string> }>;
}

export const CONTACTS_CONTEXT: InjectionKey<ContactsContext> = Symbol('joinotify-contacts');

/**
 * Reads the shared context inside a tab component.
 *
 * @since 2.5.0
 * @returns {ContactsContext} The context provided by ContactsPage.
 */
export function useContactsContext(): ContactsContext {
  const context = inject(CONTACTS_CONTEXT);

  if (!context) {
    throw new Error('The contacts context is only available inside ContactsPage.');
  }

  return context;
}

/**
 * The message to show for a failed request.
 *
 * @since 2.5.0
 * @param {unknown} error The caught error.
 * @param {string} [fallback] Message when the error carries none.
 * @returns {string} A message for the screen.
 */
export function errorMessage(error: unknown, fallback = ''): string {
  if (error instanceof CloudRequestError) {
    if (error.type === 'rate_limit' && error.retryAfter > 0) {
      /* translators: %d: seconds to wait */
      return sprintf(__('Too many requests to the Joinotify platform. Try again in %d seconds.', textDomain), error.retryAfter);
    }

    return error.message;
  }

  if (error instanceof Error && error.message) {
    return error.message;
  }

  return fallback || __('Request failed.', textDomain);
}
