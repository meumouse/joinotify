const textDomain = 'joinotify';

function getWpI18n() {
  return globalThis?.wp?.i18n || null;
}

export function __(text, domain = textDomain) {
  const translator = getWpI18n()?.__;

  if (typeof translator === 'function') {
    return translator(text, domain);
  }

  return text;
}

export function sprintf(message, ...args) {
  const formatter = getWpI18n()?.sprintf;

  if (typeof formatter === 'function') {
    return formatter(message, ...args);
  }

  return message;
}

export { textDomain };
