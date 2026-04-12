export function createApiClient(bootstrap) {
  const root = bootstrap?.rest?.root || '';
  const nonce = bootstrap?.rest?.nonce || '';

  async function request(path, options = {}) {
    const url = new URL(path.replace(/^\//, ''), `${root}/`);
    const response = await fetch(url.toString(), {
      method: options.method || 'GET',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(nonce ? { 'X-WP-Nonce': nonce } : {}),
        ...(options.headers || {}),
      },
      body: options.body ? JSON.stringify(options.body) : undefined,
      credentials: 'same-origin',
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
      const message = data?.message || 'Request failed.';
      throw new Error(message);
    }

    return data;
  }

  return {
    request,
    get: (path) => request(path),
    post: (path, body) => request(path, { method: 'POST', body }),
  };
}
