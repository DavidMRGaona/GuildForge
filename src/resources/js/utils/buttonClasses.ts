export type ButtonSize = 'sm' | 'md' | 'lg';

/**
 * Size classes of a button, shared by `BaseButton` and the footer's `core.button` block so that
 * both measure the same (core#734). Colours stay with each component.
 */
export const buttonSizeClasses: Record<ButtonSize, string> = {
    sm: 'px-4 py-2 text-sm min-h-[44px] min-w-[44px]',
    md: 'px-4 py-2 text-base min-h-[44px]',
    lg: 'px-6 py-3 text-lg min-h-[44px]',
};
