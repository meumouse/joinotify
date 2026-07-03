/**
 * queue.js frontend source file.
 *
 * @since 2.0.0
 */
import '../styles/main.css';
import '../pages/queue/styles.css';
import QueuePage from '../pages/queue/QueuePage.vue';
import { mountPage } from '../utils/bootstrap';

mountPage('joinotify-queue-app', QueuePage);
