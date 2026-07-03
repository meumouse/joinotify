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

declare module 'vue3-emoji-picker';
declare module 'vue3-emoji-picker/css';
