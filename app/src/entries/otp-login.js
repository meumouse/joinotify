/**
 * otp-login.js public entry.
 *
 * Mounts the passwordless login widget and the account phone field on the
 * front-end. The bundle is enqueued on demand by the shortcode and the
 * WooCommerce login templates (see Otp_Login\Frontend_Assets).
 *
 * @since 2.0.0
 */
import { createApp } from 'vue';
import AccountPhoneField from '../otp-login/components/AccountPhoneField.vue';
import OtpLoginApp from '../otp-login/components/OtpLoginApp.vue';
import '../otp-login/style.css';

/**
 * WordPress i18n fallback used when the global helper is unavailable.
 *
 * @since 2.0.0
 * @type {Function}
 */
const __ = window.wp?.i18n?.__ ?? ((text) => text);

/**
 * Read the trimmed text content from the first matching element.
 *
 * @since 2.0.0
 * @param {HTMLElement} el Root element.
 * @param {string} selector CSS selector to resolve.
 * @return {string} Trimmed text content or an empty string.
 */
function readText(el, selector) {
  const target = el.querySelector(selector);
  return target ? target.textContent.trim() : '';
}

/**
 * Toggle the checkout login widget visibility while the modal is open.
 *
 * @since 2.0.0
 * @param {boolean} visible Whether the widget should be shown.
 * @return {void}
 */
function setCheckoutLoginVisibility(visible) {
  document
    .querySelectorAll('[data-joinotify-otp-login][data-context="checkout"][data-hidden-until-modal="1"]')
    .forEach((root) => {
      root.style.display = visible ? '' : 'none';
      root.setAttribute('aria-hidden', visible ? 'false' : 'true');
    });
}

/**
 * Check whether the checkout login modal is currently open.
 *
 * @since 2.0.0
 * @return {boolean} True when the popup contains the checkout login widget.
 */
function isCheckoutLoginModalOpen() {
  return Boolean(
    document.querySelector(
      '.mfp-content .woocommerce-form-login, .mfp-content [data-joinotify-otp-login][data-context="checkout"]',
    ),
  );
}

/**
 * Mount the Vue login application on a single DOM root.
 *
 * @since 2.0.0
 * @param {HTMLElement} root Mount target.
 * @return {void}
 */
function mountOtpLoginApp(root) {
  const context = root.dataset.context || 'myaccount';
  const otpLength = Number.parseInt(root.dataset.otpLength || '6', 10) || 6;
  const defaultCountry = root.dataset.defaultCountry || 'br';
  const redirectUrl = root.dataset.redirectUrl || window.location.href;
  const showHeader = root.dataset.showHeader === '1';
  const apiBaseUrl = window.joinotifyOtpLogin?.restUrl || '';
  let strings = {};

  try {
    strings = root.dataset.i18n ? JSON.parse(root.dataset.i18n) : {};
  } catch (error) {
    strings = {};
  }

  const app = createApp(OtpLoginApp, {
    context,
    otpLength,
    defaultCountry,
    apiBaseUrl,
    redirectUrl,
    showHeader,
    title: root.dataset.title || readText(root, '.joinotify-otp-login__title'),
    description: root.dataset.description || readText(root, '.joinotify-otp-login__description'),
    strings,
  });

  app.config.globalProperties.__ = __;
  app.mount(root);
}

/**
 * Mount the WooCommerce account phone field on a single DOM root.
 *
 * @since 2.0.0
 * @param {HTMLElement} root Mount target.
 * @return {void}
 */
function mountAccountPhoneField(root) {
  const app = createApp(AccountPhoneField, {
    defaultCountry: root.dataset.defaultCountry || 'br',
    initialPhone: root.dataset.initialPhone || '',
  });

  app.config.globalProperties.__ = __;
  app.mount(root);
}

/**
 * Mount every OTP login instance present on the page.
 *
 * @since 2.0.0
 * @return {void}
 */
function boot() {
  if (!window.joinotifyOtpLogin) {
    return;
  }

  document.querySelectorAll('[data-joinotify-otp-login]').forEach((root) => {
    if (root.dataset.context === 'checkout' && root.dataset.hiddenUntilModal === '1') {
      root.style.display = 'none';
      root.setAttribute('aria-hidden', 'true');
    }

    mountOtpLoginApp(root);
  });

  const accountPhoneRoot = document.getElementById('joinotify-account-phone');

  if (accountPhoneRoot) {
    mountAccountPhoneField(accountPhoneRoot);
  }

  if (window.jQuery && window.jQuery.magnificPopup) {
    const $ = window.jQuery;

    $(document).on('mfpOpen.joinotifyOtpLogin', () => {
      if (isCheckoutLoginModalOpen()) {
        setCheckoutLoginVisibility(true);
      }
    });

    $(document).on('mfpClose.joinotifyOtpLogin', () => {
      setCheckoutLoginVisibility(false);
    });
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}
