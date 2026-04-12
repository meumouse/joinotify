import { createApp } from 'vue';

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
