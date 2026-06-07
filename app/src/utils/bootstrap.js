import { createApp } from 'vue';
import { createPinia } from 'pinia';

/**
 * Read the minimal bootstrap config exposed by WordPress via wp_localize_script.
 *
 * The heavy page payload is no longer embedded in the DOM. Instead WordPress
 * localizes only what the client needs to fetch it: the REST root, a nonce, the
 * page slug, and the endpoint to request.
 *
 * @since 2.0.0
 * @return {Object|null} Bootstrap config or null when it is unavailable.
 */
export function readBootstrapConfig() {
  const config = globalThis.joinotifyBootstrapConfig;

  return config && typeof config === 'object' ? config : null;
}

/**
 * Read legacy bootstrap data from a mount element's data attribute.
 *
 * Kept as a fallback for environments where the localized config is missing.
 *
 * @since 1.4.7
 * @version 2.0.0
 * @param {string} mountId - DOM id of the mount point.
 * @return {Object} Parsed bootstrap payload.
 */
export function readBootstrap(mountId) {
  const mount = document.getElementById(mountId);

  if (!mount) {
    return {};
  }

  const raw = mount.dataset.bootstrap || '{}';

  try {
    return JSON.parse(raw);
  } catch (error) {
    return {};
  }
}

/**
 * Fetch the page bootstrap payload from the REST API.
 *
 * @since 2.0.0
 * @param {Object} config - Bootstrap config from readBootstrapConfig().
 * @return {Promise<Object>} Parsed bootstrap payload.
 */
async function fetchBootstrap(config) {
  const root = String(config.restUrl || '').replace(/\/$/, '');
  const endpoint = String(config.endpoint || '').replace(/^\//, '');
  const url = `${root}/${endpoint}`;

  const response = await fetch(url, {
    method: 'GET',
    headers: {
      Accept: 'application/json',
      ...(config.nonce ? { 'X-WP-Nonce': config.nonce } : {}),
    },
    credentials: 'same-origin',
  });

  if (!response.ok) {
    throw new Error(`Bootstrap request failed (${response.status}).`);
  }

  return response.json();
}

/**
 * Render a minimal error message into the mount element.
 *
 * @since 2.0.0
 * @param {HTMLElement} mount - Mount element.
 * @param {string} message - Message to display.
 * @return {void}
 */
function renderBootstrapError(mount, message) {
  mount.innerHTML = '';

  const notice = document.createElement('div');
  notice.className = 'joinotify-bootstrap-error';
  notice.setAttribute('role', 'alert');
  notice.textContent = message;

  mount.appendChild(notice);
}

/**
 * Mount a Vue page component, resolving its bootstrap payload via a GET request.
 *
 * The skeleton already rendered inside the mount element stays visible until the
 * request resolves; Vue replaces it once the component mounts.
 *
 * @since 1.4.7
 * @version 2.0.0
 * @param {string} mountId - DOM id of the mount point.
 * @param {Object} component - Vue component to mount.
 * @return {Promise<import('vue').App | null>} Mounted Vue application instance or null when unavailable.
 */
export async function mountPage(mountId, component) {
  const mount = document.getElementById(mountId);

  if (!mount) {
    return null;
  }

  const config = readBootstrapConfig();
  let bootstrap = {};

  if (config) {
    try {
      bootstrap = await fetchBootstrap(config);

      if (config.page) {
        bootstrap.page = config.page;
      }
    } catch (error) {
      renderBootstrapError(mount, 'Could not load this page. Please reload and try again.');

      return null;
    }
  } else {
    // Fallback to the legacy inline data attribute when no config is present.
    bootstrap = readBootstrap(mountId);
  }

  const app = createApp(component, {
    bootstrap,
  });

  app.use(createPinia());

  return app.mount(mount);
}
