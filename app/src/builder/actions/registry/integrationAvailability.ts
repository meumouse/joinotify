/**
 * integrationAvailability.ts
 *
 * Reactive, module-level mirror of the plugin settings that gate integration
 * actions (`bootstrap.settings`).
 *
 * The backend only publishes an integration's actions while its toggle is on —
 * every `Joinotify/Builder/Actions` filter in admin/src/Integrations/ is wrapped
 * in an `Admin::get_setting()` check. The frontend, in contrast, registers the
 * bundled definitions eagerly during bootstrap so their Vue settings components
 * and brand icons exist even before (or without) the catalog request. Without
 * this gate a disabled channel such as Telegram or Resend would still show up in
 * the action library, offering a step the runtime would never deliver.
 *
 * Definitions declare the setting they depend on through `requiresSetting`; the
 * registry hides them from the catalog while that setting is not `yes`. Single
 * action lookups stay ungated on purpose: a workflow saved while the channel was
 * enabled must keep rendering its nodes after it is turned off.
 *
 * @since 2.3.0
 */
import { ref } from 'vue';

const settings = ref<Record<string, unknown>>({});
const hydrated = ref(false);

/**
 * Populates the cache from the bootstrap settings payload.
 *
 * @since 2.3.0
 * @param {unknown} value The `settings` object from the builder bootstrap.
 * @returns {void}
 */
export function setIntegrationAvailability(value: unknown): void {
  if (!value || typeof value !== 'object' || Array.isArray(value)) {
    settings.value = {};
    hydrated.value = false;

    return;
  }

  settings.value = { ...(value as Record<string, unknown>) };
  hydrated.value = true;
}

/**
 * Indicates whether a boolean-ish plugin setting is turned on.
 *
 * Settings are stored as the `yes`/`no` strings WordPress options use, but the
 * REST payload may already have coerced them into booleans.
 *
 * @since 2.3.0
 * @param {string} key Setting key (e.g. `enable_telegram_integration`).
 * @returns {boolean} True when the setting is enabled.
 */
export function isSettingEnabled(key: string): boolean {
  const value = settings.value[String(key || '').trim()];

  if (typeof value === 'boolean') {
    return value;
  }

  return String(value ?? '').trim().toLowerCase() === 'yes';
}

/**
 * Indicates whether an action gated behind a setting may be listed.
 *
 * Ungated actions are always available, and so is everything else until the
 * bootstrap settings arrive — hiding the whole library on a failed or pending
 * bootstrap would be worse than briefly listing a disabled channel.
 *
 * @since 2.3.0
 * @param {string} [requiresSetting] Setting key the action depends on.
 * @returns {boolean} True when the action may be offered.
 */
export function isActionAvailable(requiresSetting?: string): boolean {
  const key = String(requiresSetting || '').trim();

  if (!key || !hydrated.value) {
    return true;
  }

  return isSettingEnabled(key);
}

/**
 * Returns the current availability revision source, so registry consumers that
 * read the catalog inside a computed re-evaluate when the settings change.
 *
 * @since 2.3.0
 * @returns {boolean} True once the bootstrap settings have been applied.
 */
export function isIntegrationAvailabilityHydrated(): boolean {
  return hydrated.value;
}
