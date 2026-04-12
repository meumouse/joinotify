import { createApp } from 'vue';

/**
 * Read bootstrap data from a mount element.
 *
 * @since 1.4.7
 * @version 1.4.7
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
 * Mount a Vue page component into the requested DOM element.
 *
 * @since 1.4.7
 * @version 1.4.7
 * @param {string} mountId - DOM id of the mount point.
 * @param {Object} component - Vue component to mount.
 * @return {import('vue').App | null} Mounted Vue application instance or null when the target is missing.
 */
export function mountPage(mountId, component) {
  const mount = document.getElementById(mountId);

  if (!mount) {
    return null;
  }

  const bootstrap = readBootstrap(mountId);

  return createApp(component, {
    bootstrap,
  }).mount(mount);
}
