import { createApp } from 'vue';
import App from './App.vue';
import './styles/main.css';

function readBootstrap() {
  const mount = document.getElementById('joinotify-settings-app') || document.getElementById('joinotify-license-app');

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

const mount = document.getElementById('joinotify-settings-app') || document.getElementById('joinotify-license-app');

if (mount) {
  createApp(App, {
    bootstrap: readBootstrap(),
  }).mount(mount);
}
