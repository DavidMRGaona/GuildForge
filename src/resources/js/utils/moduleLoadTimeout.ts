/**
 * How long a lazy module slot component may take to load before Vue gives up on it. Shared by
 * `useModuleSlots` and by the cookie consent contract, which waits at least this long for the
 * banner (a slot component) to register its provider.
 */
export const SLOT_COMPONENT_TIMEOUT_MS = 15000;
