/**
 * onboarding.js frontend source file.
 *
 * @since 2.3.0
 */
import '../styles/main.css';
import '../pages/onboarding/styles.css';
import OnboardingPage from '../pages/onboarding/OnboardingPage.vue';
import { mountPage } from '../utils/bootstrap';

mountPage('joinotify-onboarding-app', OnboardingPage);
