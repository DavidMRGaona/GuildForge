import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';

// Kept apart from vite.config.ts: the tests need neither the Laravel plugin nor the module build
export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': resolve(import.meta.dirname, 'resources/js'),
        },
    },
    test: {
        environment: 'happy-dom',
        include: ['resources/js/**/__tests__/**/*.spec.ts', 'scripts/__tests__/**/*.spec.ts'],
        restoreMocks: true,
    },
});
