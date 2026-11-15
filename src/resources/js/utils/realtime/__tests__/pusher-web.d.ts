// The browser build of pusher-js, which the page gets and `client.pusher.spec.ts` loads by path
// (under Vitest the bare `pusher-js` resolves to the Node build). Same API as the package entry.
declare module 'pusher-js/dist/web/pusher.js' {
    export { default } from 'pusher-js';
}
