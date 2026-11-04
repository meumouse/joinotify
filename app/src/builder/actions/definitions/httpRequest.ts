/**
 * httpRequest.ts
 *
 * Builder action definition for the "HTTP request" action, which calls any API
 * and keeps its response on the payload under a name, for the later actions to
 * read through {{ http:NAME.path }}. Provides data normalization, the canvas
 * description, and validation. The runtime lives in
 * admin/src/Builder/Http_Request_Action.php.
 *
 * @since 2.7.0
 */
import HttpRequestSettings from '../settings/HttpRequestSettings.vue';
import { normalizeValidationErrors, requiredFieldErrors } from '../utils/validators';
import type { ActionDefinition } from '../registry/types';
import { HTTP_REQUEST_ICON } from './actionIcons';
import { __, sprintf, textDomain } from '../../../utils/i18n';

export interface HttpRequestRow {
  key: string;
  value: string;
  // Only on the JSON body fields: how the value is sent (see JSON_FIELD_TYPES).
  type?: string;
}

// Types a JSON body field is sent as, as Http_Request_Action::FIELD_TYPES.
export const JSON_FIELD_TYPES = ['text', 'number', 'boolean', 'null', 'json'];

const BOOLEAN_WORDS = ['true', '1', 'yes', 'on', 'false', '0', 'no', 'off', ''];

export interface JsonBodyCheck {
  // Translated syntax error, empty when the body is valid JSON.
  error: string;
  // Placeholders typed outside quotes, which must become a JSON value once replaced.
  bareTokens: string[];
}

const PLACEHOLDER_PATTERN = /\{\{[\s\S]*?\}\}/g;

export const HTTP_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

// Prefix of a credential sealed by the server's Token_Vault.
export const SEALED_PREFIX = 'enc:v1:';

/**
 * Normalizes a list field into { key, value } rows, dropping anything else.
 *
 * @since 2.7.0
 * @param {unknown} value Raw rows.
 * @returns {HttpRequestRow[]} The rows.
 */
export function normalizeRows(value: unknown): HttpRequestRow[] {
  const source = Array.isArray(value) ? value : value && typeof value === 'object' ? Object.values(value) : [];

  return source
    .filter((row): row is Record<string, unknown> => Boolean(row) && typeof row === 'object')
    .map((row) => ({ key: String(row.key ?? ''), value: String(row.value ?? '') }));
}

/**
 * Normalizes the JSON body fields into { key, value, type } rows, an unknown
 * type read as text.
 *
 * @since 2.7.1
 * @param {unknown} value Raw rows.
 * @returns {HttpRequestRow[]} The rows.
 */
export function normalizeJsonFields(value: unknown): HttpRequestRow[] {
  const source = Array.isArray(value) ? value : value && typeof value === 'object' ? Object.values(value) : [];

  return source
    .filter((row): row is Record<string, unknown> => Boolean(row) && typeof row === 'object')
    .map((row) => ({
      key: String(row.key ?? ''),
      value: String(row.value ?? ''),
      type: JSON_FIELD_TYPES.includes(String(row.type)) ? String(row.type) : 'text',
    }));
}

/**
 * Checks the JSON body fields for what can already be told before the
 * placeholders are replaced: a value without a name, a repeated name, and a
 * number, boolean or JSON value that can never become its type. A value with a
 * placeholder is only checked once the workflow runs.
 *
 * @since 2.7.1
 * @param {unknown} fields The rows.
 * @returns {string[]} Translated errors, empty when none.
 */
export function checkJsonFields(fields: unknown): string[] {
  const errors: string[] = [];
  const seen = new Set<string>();

  for (const row of normalizeJsonFields(fields)) {
    const key = row.key.trim();
    const value = row.value.trim();
    const hasPlaceholder = value.includes('{{');

    if (!key) {
      if (value) {
        errors.push(__('A field has a value but no name.', textDomain));
      }

      continue;
    }

    if (seen.has(key)) {
      /* translators: %s: field name. */
      errors.push(sprintf(__('The name "%s" is used more than once; only the last one is sent.', textDomain), key));
    }

    seen.add(key);

    if (row.type === 'number' && !hasPlaceholder && (value === '' || !Number.isFinite(Number(value)))) {
      /* translators: %s: field name. */
      errors.push(sprintf(__('"%s" is set as a number, but its value is not one.', textDomain), key));
    } else if (row.type === 'boolean' && !hasPlaceholder && !BOOLEAN_WORDS.includes(value.toLowerCase())) {
      /* translators: %s: field name. */
      errors.push(sprintf(__('"%s" is set as a boolean: use true or false.', textDomain), key));
    } else if (row.type === 'json') {
      const check = checkJsonBody(value);

      if (!value || check.error) {
        /* translators: %s: field name. */
        errors.push(sprintf(__('"%s" is set as JSON, but its value is not valid JSON.', textDomain), key));
      }
    }
  }

  return errors;
}

/**
 * Writes the JSON body fields as JSON text, for switching from the table to
 * typing the JSON. A number or boolean with a placeholder is written outside
 * quotes, the way the table sends it.
 *
 * @since 2.7.1
 * @param {unknown} fields The rows.
 * @returns {string} The JSON text, empty when there are no named fields.
 */
export function jsonFieldsToText(fields: unknown): string {
  const lines = normalizeJsonFields(fields)
    .filter((row) => row.key.trim())
    .map((row) => {
      const value = row.value.trim();
      let text = JSON.stringify(row.value);

      if (row.type === 'null') {
        text = 'null';
      } else if (row.type === 'json') {
        text = value || 'null';
      } else if (row.type === 'number' && value) {
        text = value;
      } else if (row.type === 'boolean') {
        text = value.includes('{{') ? value : String(['true', '1', 'yes', 'on'].includes(value.toLowerCase()));
      }

      return `  ${JSON.stringify(row.key.trim())}: ${text.split('\n').join('\n  ')}`;
    });

  return lines.length ? `{\n${lines.join(',\n')}\n}` : '';
}

/**
 * Reads a JSON object typed as text into fields, for switching from typing the
 * JSON to the table. Null when the text is not a plain JSON object, such as one
 * with a placeholder outside quotes.
 *
 * @since 2.7.1
 * @param {unknown} text The JSON text.
 * @returns {HttpRequestRow[] | null} The rows.
 */
export function jsonTextToFields(text: unknown): HttpRequestRow[] | null {
  let decoded: unknown;

  try {
    decoded = JSON.parse(String(text ?? ''));
  } catch {
    return null;
  }

  if (!decoded || typeof decoded !== 'object' || Array.isArray(decoded)) {
    return null;
  }

  return Object.entries(decoded as Record<string, unknown>).map(([key, value]) => {
    if (value === null) {
      return { key, value: '', type: 'null' };
    }

    if (typeof value === 'object') {
      return { key, value: JSON.stringify(value), type: 'json' };
    }

    const types: Record<string, string> = { number: 'number', boolean: 'boolean' };

    return { key, value: String(value), type: types[typeof value] || 'text' };
  });
}

/**
 * Turns a typed name into the response variable slug the server keeps, the
 * same way sanitize_key() does: lowercase letters, numbers, - and _.
 *
 * @since 2.7.0
 * @param {unknown} value Typed name.
 * @returns {string} The slug.
 */
export function responseVarSlug(value: unknown): string {
  return String(value ?? '').toLowerCase().replace(/[^a-z0-9_-]/g, '');
}

/**
 * Turns a JSON.parse error into a 1-based line and column of the text, from the
 * "line L column C" (Chrome, Firefox) or "position N" (older Chrome) the engines
 * report. Null when the engine gives neither (Safari).
 *
 * @since 2.7.1
 * @param {string} text The parsed text.
 * @param {unknown} error What JSON.parse threw.
 * @returns {{ line: number, column: number } | null} Where the error is.
 */
function jsonErrorLocation(text: string, error: unknown): { line: number; column: number } | null {
  const message = error instanceof Error ? error.message : String(error ?? '');
  const lineColumn = message.match(/line (\d+) column (\d+)/i);

  if (lineColumn) {
    return { line: Number(lineColumn[1]), column: Number(lineColumn[2]) };
  }

  const position = message.match(/position (\d+)/i);

  if (!position) {
    return null;
  }

  const before = text.slice(0, Number(position[1])).split('\n');

  return { line: before.length, column: before[before.length - 1].length + 1 };
}

/**
 * Checks a JSON body template the way the server fills it
 * (Http_Request_Action::fill_json): a placeholder inside quotes is text of that
 * string, and one outside quotes stands for the value it becomes, so it counts
 * as a number here. Every placeholder is masked with text of the same length,
 * so the line and column of an error point at what was typed.
 *
 * @since 2.7.1
 * @param {unknown} template The typed JSON.
 * @returns {JsonBodyCheck} The syntax error, if any, and the placeholders outside quotes.
 */
export function checkJsonBody(template: unknown): JsonBodyCheck {
  const text = String(template ?? '');
  const tokens = [...text.matchAll(PLACEHOLDER_PATTERN)].map((match) => ({ start: match.index ?? 0, value: match[0] }));
  const bareTokens: string[] = [];

  if (!text.trim()) {
    return { error: '', bareTokens };
  }

  // Mask the tokens first, so a quote inside one ({{ x | remove:"a" }}) never flips the string state.
  let masked = text.replace(PLACEHOLDER_PATTERN, (token) => token.replace(/[^\n]/g, 'x'));
  let inString = false;
  let escaped = false;
  let next = 0;

  for (let index = 0; index < masked.length && next < tokens.length; index++) {
    if (index === tokens[next].start) {
      if (!inString) {
        const token = tokens[next].value;

        bareTokens.push(token);
        masked = masked.slice(0, index) + '0' + token.slice(1).replace(/[^\n]/g, ' ') + masked.slice(index + token.length);
      }

      index += tokens[next].value.length - 1;
      next++;
      continue;
    }

    const char = masked[index];

    if (escaped) {
      escaped = false;
    } else if (char === '\\' && inString) {
      escaped = true;
    } else if (char === '"') {
      inString = !inString;
    }
  }

  try {
    JSON.parse(masked);

    return { error: '', bareTokens };
  } catch (error) {
    const location = jsonErrorLocation(masked, error);

    return {
      error: location
        ? sprintf(
          /* translators: 1: line number, 2: column number. */
          __('The JSON is not valid near line %1$d, column %2$d. Look for a missing or extra comma, quote, bracket or brace.', textDomain),
          location.line,
          location.column,
        )
        : __('The JSON is not valid. Look for a missing or extra comma, quote, bracket or brace.', textDomain),
      bareTokens,
    };
  }
}

/**
 * Normalizes/serializes the HTTP request action payload, applying defaults.
 *
 * @since 2.7.0
 * @version 2.7.1
 * @param {Record<string, unknown>} data Raw action data.
 * @returns {Record<string, unknown>} Normalized HTTP request action data.
 */
function normalizeHttpRequestData(data: Record<string, unknown>): Record<string, unknown> {
  const method = String(data.method || 'GET').toUpperCase();
  // A node saved before the table existed has no json_input: it keeps the JSON it was written with.
  const jsonInput = data.json_input === 'fields' || data.json_input === 'raw'
    ? data.json_input
    : String(data.body || '').trim() ? 'raw' : 'fields';

  return {
    title: String(data.title || __('HTTP request', textDomain)),
    description: String(data.description || ''),
    action: 'http_request',
    method: HTTP_METHODS.includes(method) ? method : 'GET',
    url: String(data.url || ''),
    query: normalizeRows(data.query),
    headers: normalizeRows(data.headers),
    auth_type: String(data.auth_type || 'none'),
    auth_user: String(data.auth_user || ''),
    auth_header: String(data.auth_header ?? 'X-API-Key'),
    auth_value: String(data.auth_value || ''),
    body_mode: String(data.body_mode || 'none'),
    json_input: jsonInput,
    json_fields: normalizeJsonFields(data.json_fields),
    body: String(data.body || ''),
    body_fields: normalizeRows(data.body_fields),
    content_type: String(data.content_type || 'text/plain'),
    timeout: String(data.timeout ?? '10'),
    response_var: responseVarSlug(data.response_var),
    on_error: data.on_error === 'stop' ? 'stop' : 'continue',
  };
}

/**
 * Builds the canvas line: method, host and the response token.
 *
 * @since 2.7.0
 * @param {Record<string, unknown>} data Action data.
 * @returns {string} The description.
 */
function describeHttpRequest(data: Record<string, unknown>): string {
  let host = '';

  try {
    host = new URL(String(data.url || '')).host;
  } catch {
    host = '';
  }

  if (!host) {
    return __('Set the URL', textDomain);
  }

  const name = responseVarSlug(data.response_var);
  const line = `${String(data.method || 'GET').toUpperCase()} ${host}`;

  return name ? `${line} → {{ http:${name} }}` : line;
}

/**
 * Validates the URL, the JSON body (its syntax, or its fields) when one is
 * expected and the credential.
 *
 * @since 2.7.0
 * @version 2.7.1
 * @param {Record<string, unknown>} data Action data.
 * @returns {Record<string, string>} Validation errors.
 */
function validateHttpRequest(data: Record<string, unknown>) {
  const errors = requiredFieldErrors(data, [{ key: 'url', label: __('URL', textDomain) }]);
  const method = String(data.method || 'GET').toUpperCase();

  if (method !== 'GET' && data.body_mode === 'json' && data.json_input === 'fields') {
    errors.json_fields = checkJsonFields(data.json_fields)[0] || '';
  } else if (method !== 'GET' && data.body_mode === 'json') {
    errors.body = String(data.body || '').trim()
      ? checkJsonBody(data.body).error
      : __('Type the JSON to send.', textDomain);
  }

  if (data.auth_type && data.auth_type !== 'none' && !String(data.auth_value || '').trim()) {
    errors.auth_value = __('Type the credential, or choose no authentication.', textDomain);
  }

  return normalizeValidationErrors(errors);
}

export const httpRequestDefinition: ActionDefinition = {
  action: 'http_request',
  title: __('HTTP request', textDomain),
  description: __('Call any API and use its response in the next actions.', textDomain),
  icon: 'globe',
  iconSvg: HTTP_REQUEST_ICON,
  category: 'general',
  hasSettings: true,
  priority: 79,
  isExpansible: true,
  defaultData: normalizeHttpRequestData({}),
  settingsComponent: HttpRequestSettings,
  normalizeData: normalizeHttpRequestData,
  serializeData: normalizeHttpRequestData,
  buildDescription: describeHttpRequest,
  validate: validateHttpRequest,
};
