/**
 * deprecation.ts
 *
 * Shared copy for deprecated builder actions. Mirrors
 * `Integrations\Deprecated_Integrations` on the backend, so the builder and the
 * settings screen show the same message (and share one translation).
 *
 * @since 2.8.0
 */
import { __, sprintf, textDomain } from '../../../utils/i18n';

/** Joinotify version that removes the deprecated integrations. */
export const DEPRECATED_INTEGRATIONS_REMOVAL_VERSION = '3.0.0';

/**
 * Builds the deprecation message for an integration.
 *
 * @since 2.8.0
 * @param {string} label Integration name (e.g. Telegram).
 * @returns {string} The translated message.
 */
export function deprecatedIntegrationMessage(label: string): string {
  return sprintf(
    // translators: 1: integration name (e.g. Telegram), 2: Joinotify version that removes it
    __('%1$s is deprecated and will be removed in Joinotify %2$s. Workflows that use it keep sending until then; move them to WhatsApp.', textDomain),
    label,
    DEPRECATED_INTEGRATIONS_REMOVAL_VERSION
  );
}
