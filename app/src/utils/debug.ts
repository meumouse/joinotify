type DebugEnabledSource = boolean | { value?: boolean } | (() => boolean);

function resolveDebugEnabled(source: DebugEnabledSource): boolean {
  if (typeof source === 'function') {
    return Boolean(source());
  }

  if (source && typeof source === 'object' && 'value' in source) {
    return Boolean(source.value);
  }

  return Boolean(source);
}

export function createDebugLogger(scope: string, enabledSource: DebugEnabledSource) {
  const prefix = `[Joinotify][${scope}]`;

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
