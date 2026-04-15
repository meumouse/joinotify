const textDomain = 'joinotify';

function getWpI18n() {
  return globalThis?.wp?.i18n || null;
}

export function __(text: string, domain = textDomain): string {
  const translator = getWpI18n()?.__;

  if (typeof translator === 'function') {
    return translator(text, domain);
  }

  return text;
}

export function sprintf(message: string, ...args: unknown[]): string {
  const formatter = getWpI18n()?.sprintf;

  if (typeof formatter === 'function') {
    return formatter(message, ...args);
  }

  return message;
}

export { textDomain };
