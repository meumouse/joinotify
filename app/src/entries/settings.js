import '../styles/main.css';
import '../pages/settings/styles.css';
import SettingsPage from '../pages/settings/SettingsPage.vue';
import { mountPage } from '../utils/bootstrap';

mountPage('joinotify-settings-app', SettingsPage);
