/**
 * Re-export all Vue exports for module runtime loading.
 *
 * Modules built with Vue as external need the browser to resolve
 * `import { ... } from 'vue'` via import maps. This file creates
 * an entry point that explicitly exports everything from Vue,
 * so the built chunk can be used with import maps.
 */
export * from 'vue';
