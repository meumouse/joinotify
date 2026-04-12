import { createApp } from 'vue';
import App from './App.vue';
import './styles/main.css';

function readBootstrap() {
  const mount = document.getElementById('joinotify-settings-app');

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

createApp(App, {
  bootstrap: readBootstrap(),
}).mount('#joinotify-settings-app');
