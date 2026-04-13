export function escapeHtml(value: string): string {
  return String(value || '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
}

export function sanitizePreviewHtml(value: string): string {
  const source = String(value || '');
  const strippedScripts = source.replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, '');
  const strippedHandlers = strippedScripts.replace(/\son[a-z]+\s*=\s*(".*?"|'.*?'|[^\s>]+)/gi, '');

  return strippedHandlers
    .replace(/<(?!\/?(strong|em|b|i|u|br|p|ul|ol|li|a)(\s|>|\/))/gi, '&lt;')
    .replace(/\n/g, '<br>');
}
