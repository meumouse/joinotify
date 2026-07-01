/**
 * debug.ts
 *
 * Lightweight scoped debug logger. Emits namespaced `console.info` messages
 * only when the provided enabled source resolves to true, supporting boolean,
 * ref-like, and getter-function sources.
 *
 * @since 2.0.0
 */
type DebugEnabledSource = boolean | { value?: boolean } | (() => boolean);

/**
 * Resolves whether debug logging is enabled from the given source.
 *
 * @since 2.0.0
 * @param {DebugEnabledSource} source Boolean, ref-like, or getter source.
 * @returns {boolean} True when debug logging is enabled.
 */
function resolveDebugEnabled(source: DebugEnabledSource): boolean {
  if (typeof source === 'function') {
    return Boolean(source());
  }

  if (source && typeof source === 'object' && 'value' in source) {
    return Boolean(source.value);
  }

  return Boolean(source);
}

/**
 * Creates a scoped debug logger bound to a namespace and enabled source.
 *
 * @since 2.0.0
 * @param {string} scope Namespace shown in the log prefix.
 * @param {DebugEnabledSource} enabledSource Source controlling whether logs emit.
 * @returns {Object} Logger object exposing a `log` method.
 */
export function createDebugLogger(scope: string, enabledSource: DebugEnabledSource) {
  const prefix = `[Joinotify][${scope}]`;

  /**
   * Logs an event with optional details when debugging is enabled.
   *
   * @since 2.0.0
   * @param {string} event The event message.
   * @param {unknown} [details] Optional details to include.
   */
  function log(event: string, details?: unknown) {
    if (typeof console === 'undefined' || !resolveDebugEnabled(enabledSource)) {
      return;
    }

    if (typeof details === 'undefined') {
      console.info(prefix, event);
      return;
    }

    console.info(prefix, event, details);
  }

  return {
    log,
  };
}
