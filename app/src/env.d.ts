/**
 * env.d.ts
 *
 * Ambient TypeScript declarations for the Vite build. Declares the `*.vue`
 * single-file component module type and third-party modules that ship without
 * their own type definitions.
 *
 * @since 2.0.0
 */
/// <reference types="vite/client" />

declare module '*.vue' {
  import type { DefineComponent } from 'vue';

  const component: DefineComponent<Record<string, unknown>, Record<string, unknown>, unknown>;
  export default component;
}

/**
 * The WordPress `wp` global, narrowed to the `wp.i18n` helpers the app calls.
 * The runtime may be missing when `wp-i18n` is not loaded on the page, so
 * every read goes through optional chaining.
 */
interface JoinotifyWpI18n {
  __?: (text: string, domain?: string) => string;
  _n?: (single: string, plural: string, number: number, domain?: string) => string;
  sprintf?: (format: string, ...args: unknown[]) => string;
}

declare var wp: { i18n?: JoinotifyWpI18n } | undefined;

declare module 'vue3-emoji-picker';
declare module 'vue3-emoji-picker/css';
