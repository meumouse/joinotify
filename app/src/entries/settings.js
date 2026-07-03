/**
 * settings.js frontend source file.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
import '../styles/main.css';
import '../pages/settings/styles.css';
import SettingsPage from '../pages/settings/SettingsPage.vue';
import { mountPage } from '../utils/bootstrap';

mountPage('joinotify-settings-app', SettingsPage);
