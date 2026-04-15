function cleanText(value: unknown): string {
  return String(value ?? '').replace(/\s+/g, ' ').trim();
}

function joinSegments(parts: string[]): string {
  return parts.filter(Boolean).join(' · ');
}

export function describeTimeDelayAction(data: Record<string, unknown>): string {
  const mode = cleanText(data.delay_type || 'period');

  if (mode === 'date') {
    return joinSegments([
      'Delay',
      cleanText(data.date_value || ''),
      cleanText(data.time_value || ''),
    ]) || 'Delay until a fixed date';
  }

  const amount = cleanText(data.delay_value || '');
  const period = cleanText(data.delay_period || 'minute');
  return joinSegments(['Delay', amount ? `${amount} ${period}` : period]);
}

export function describeConditionAction(data: Record<string, unknown>): string {
  const condition = cleanText(data.condition || 'condition');
  const operator = cleanText(data.condition_type || '');
  const field = cleanText(data.field_id || data.meta_key || '');

  return joinSegments([
    'Condition',
    field,
    condition,
    operator,
  ]);
}

export function describeSnippetAction(data: Record<string, unknown>): string {
  const snippet = cleanText(data.snippet_php || '');

  if (!snippet) {
    return 'PHP snippet';
  }

  return joinSegments(['PHP snippet', snippet.slice(0, 48)]);
}

export function describeStopAction(): string {
  return 'Stops the workflow immediately';
}

export function describeFallbackAction(title: string, description: string, data: Record<string, unknown>): string {
  const candidate = cleanText(data.description || data.message || data.summary || '');

  if (candidate) {
    return candidate;
  }

  return joinSegments([cleanText(title), cleanText(description)]);
}

export function truncateDescription(value: string, maxLength = 120): string {
  const text = cleanText(value);

  if (text.length <= maxLength) {
    return text;
  }

  return `${text.slice(0, maxLength - 1).trimEnd()}…`;
}
