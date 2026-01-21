import js from '@eslint/js';
import typescript from '@typescript-eslint/eslint-plugin';
import typescriptParser from '@typescript-eslint/parser';
import vue from 'eslint-plugin-vue';
import vueParser from 'vue-eslint-parser';

export default [
    js.configs.recommended,
    {
        ignores: [
            'node_modules/**',
            'public/**',
            'vendor/**',
            'bootstrap/cache/**',
            'storage/**',
        ],
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
                MediaQueryList: 'readonly',
                MediaQueryListEvent: 'readonly',
                URL: 'readonly',
                File: 'readonly',
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
            vue,
            '@typescript-eslint': typescript,
        },
        rules: {
            ...vue.configs['flat/recommended'].rules,
            ...typescript.configs.recommended.rules,
            'vue/multi-word-component-names': 'off',
            'vue/no-v-html': 'warn',
            '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
            '@typescript-eslint/no-explicit-any': 'error',
        },
    },
];