import js from '@eslint/js';
import typescript from '@typescript-eslint/eslint-plugin';
import typescriptParser from '@typescript-eslint/parser';
import prettier from 'eslint-config-prettier';
import vue from 'eslint-plugin-vue';
import vueParser from 'vue-eslint-parser';

export default [
    js.configs.recommended,
    ...vue.configs['flat/recommended'],
    {
        ignores: ['node_modules/**', 'public/**', 'vendor/**', 'bootstrap/cache/**', 'storage/**'],
    },
    {
        files: ['**/*.js'],
        languageOptions: {
            parserOptions: {
                ecmaVersion: 'latest',
                sourceType: 'module',
            },
            globals: {
                console: 'readonly',
                document: 'readonly',
                window: 'readonly',
                Alpine: 'readonly',
                setTimeout: 'readonly',
                clearTimeout: 'readonly',
            },
        },
    },
    {
        files: ['**/*.ts'],
        languageOptions: {
            parser: typescriptParser,
            parserOptions: {
                ecmaVersion: 'latest',
                sourceType: 'module',
            },
            globals: {
                console: 'readonly',
                document: 'readonly',
                window: 'readonly',
                fetch: 'readonly',
                Response: 'readonly',
                MediaQueryList: 'readonly',
                MediaQueryListEvent: 'readonly',
                URL: 'readonly',
                File: 'readonly',
                HTMLLinkElement: 'readonly',
                HTMLScriptElement: 'readonly',
            },
        },
        plugins: {
            '@typescript-eslint': typescript,
        },
        rules: {
            ...typescript.configs.recommended.rules,
            '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
            '@typescript-eslint/explicit-function-return-type': 'error',
            '@typescript-eslint/no-explicit-any': 'error',
            '@typescript-eslint/strict-boolean-expressions': 'off',
        },
    },
    {
        files: ['**/*.vue'],
        languageOptions: {
            parser: vueParser,
            parserOptions: {
                parser: typescriptParser,
                ecmaVersion: 'latest',
                sourceType: 'module',
                // Vue 3 has no filters: '|' in a TypeScript cast is not one
                vueFeatures: { filter: false },
            },
            globals: {
                console: 'readonly',
                document: 'readonly',
                window: 'readonly',
                Event: 'readonly',
                MouseEvent: 'readonly',
                KeyboardEvent: 'readonly',
                HTMLElement: 'readonly',
                HTMLButtonElement: 'readonly',
                HTMLInputElement: 'readonly',
                setTimeout: 'readonly',
                clearTimeout: 'readonly',
                fetch: 'readonly',
                URLSearchParams: 'readonly',
                URL: 'readonly',
            },
        },
        plugins: {
            '@typescript-eslint': typescript,
        },
        rules: {
            ...typescript.configs.recommended.rules,
            'vue/multi-word-component-names': 'off',
            'vue/no-v-html': 'warn',
            '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
            '@typescript-eslint/no-explicit-any': 'error',
        },
    },
    // Last: turns off the formatting rules that Prettier owns
    prettier,
];
