/**
 * history.js frontend source file.
 *
 * @since 2.0.0
 */
import '../styles/main.css';
import '../pages/history/styles.css';
import HistoryPage from '../pages/history/HistoryPage.vue';
import { mountPage } from '../utils/bootstrap';

mountPage('joinotify-history-app', HistoryPage);
