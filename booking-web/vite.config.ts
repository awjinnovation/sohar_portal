import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// Static build for nginx (Dockerfile). Everything under /assets/ carries a
// content hash and is served immutable; index.html is revalidated (README.md
// "Caching and CDN").
export default defineConfig({
  plugins: [react()],
  build: {
    target: 'es2020',
    assetsDir: 'assets',
    sourcemap: false,
    cssCodeSplit: false,
    modulePreload: { polyfill: false },
  },
  server: { port: 5174 },
});
