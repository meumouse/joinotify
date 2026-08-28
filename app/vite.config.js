import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));

/**
 * vue3-emoji-picker ships a constant holding a remote base URL, used only to build
 * <img> sources for the image (non-native) emoji set. Joinotify always renders the
 * picker with `:native="true"`, so that URL is never requested — but shipping it
 * inside the bundle still reads as an external asset dependency, which the
 * WordPress.org guidelines do not allow.
 *
 * The constant is blanked here whatever address it holds, so no remote asset URL
 * reaches `app/dist` and a future release of the library cannot reintroduce one.
 */
function stripEmojiPickerRemoteAssets() {
  return {
    name: 'joinotify-strip-emoji-picker-remote-assets',
    apply: 'build',
    enforce: 'post',
    transform(code, id) {
      if (!id.includes('vue3-emoji-picker')) {
        return null;
      }

      const stripped = code.replace(/(EMOJI_REMOTE_SRC\s*=\s*)(["'])(?:(?!\2).)*\2/g, '$1$2$2');

      return stripped === code ? null : { code: stripped, map: null };
    },
  };
}

export default defineConfig({
  base: './',
  plugins: [vue(), stripEmojiPickerRemoteAssets()],
  build: {
    outDir: resolve(__dirname, 'dist'),
    emptyOutDir: true,
    manifest: true,
    sourcemap: false,
    rollupOptions: {
      input: {
        settings: resolve(__dirname, 'src/entries/settings.js'),
        onboarding: resolve(__dirname, 'src/entries/onboarding.js'),
        builder: resolve(__dirname, 'src/entries/builder.js'),
        workflows: resolve(__dirname, 'src/entries/workflows.js'),
        history: resolve(__dirname, 'src/entries/history.js'),
        queue: resolve(__dirname, 'src/entries/queue.js'),
        'otp-login': resolve(__dirname, 'src/entries/otp-login.js'),
      },
      output: {
        entryFileNames: '[name]/app.js',
        chunkFileNames: 'chunks/[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          const name = assetInfo.name || '';

          if (name.endsWith('.css')) {
            return 'styles/[name][extname]';
          }

          // WordPress.org rejects file names with special characters, and some dependencies
          // ship them (intl-tel-input's `flags@2x.webp`), so the base name is sanitized here.
          const base = (/([^/\\]+?)(\.[^.]*)?$/.exec(name)?.[1] || 'asset').replace(/[^A-Za-z0-9._-]+/g, '-');

          return `assets/${base}-[hash][extname]`;
        },
      },
    },
  },
});
