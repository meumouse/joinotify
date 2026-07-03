import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));

export default defineConfig({
  base: './',
  plugins: [vue()],
  build: {
    outDir: resolve(__dirname, 'dist'),
    emptyOutDir: true,
    manifest: true,
    sourcemap: false,
    rollupOptions: {
      input: {
        settings: resolve(__dirname, 'src/entries/settings.js'),
        license: resolve(__dirname, 'src/entries/license.js'),
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
          if (assetInfo.name && assetInfo.name.endsWith('.css')) {
            return 'styles/[name][extname]';
          }

          return 'assets/[name]-[hash][extname]';
        },
      },
    },
  },
});
