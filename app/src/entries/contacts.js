/**
 * contacts.js frontend source file.
 *
 * @since 2.5.0
 */
import '../styles/main.css';
import '../pages/contacts/styles.css';
import ContactsPage from '../pages/contacts/ContactsPage.vue';
import { mountPage } from '../utils/bootstrap';

mountPage('joinotify-contacts-app', ContactsPage);
