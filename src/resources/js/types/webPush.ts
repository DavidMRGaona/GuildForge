/**
 * Shared prop `webPush` (core#803): the VAPID public key browsers subscribe with. Null when the
 * installation has no push notifications, nobody is signed in or someone is impersonating.
 */
export interface WebPushSharedProps {
    publicKey: string;
}
