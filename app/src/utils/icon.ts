export function isSvgMarkup(value: unknown): value is string {
  return typeof value === 'string' && value.trim().startsWith('<svg');
}

export function resolveSvgMarkup(primary?: unknown, fallback?: unknown): string {
  if (isSvgMarkup(primary)) {
    return primary.trim();
  }

  if (isSvgMarkup(fallback)) {
    return fallback.trim();
  }

  return '';
}
