/**
 * builder.js frontend source file.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
import '../styles/main.css';
import BuilderPage from '../pages/builder/BuilderPage.vue';
import { mountPage } from '../utils/bootstrap';

mountPage('joinotify-builder-app', BuilderPage);
