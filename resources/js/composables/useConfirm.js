import { reactive } from 'vue';

/**
 * App-wide replacement for window.confirm(). The dialog itself is rendered once by
 * Components/Shared/ConfirmDialog.vue (mounted at the app root in app.js).
 *
 *   if (!(await confirmDialog({ title: 'Delete role?', tone: 'danger' }))) return;
 *
 * Resolves true when confirmed, false when cancelled (button, Esc or backdrop).
 *
 * @param {{ title: string, message?: string, confirmText?: string, cancelText?: string, tone?: 'primary'|'danger'|'warning' }} options
 * @returns {Promise<boolean>}
 */
const defaults = {
  title: 'Are you sure?',
  message: '',
  confirmText: 'Confirm',
  cancelText: 'Cancel',
  tone: 'primary',
};

export const confirmState = reactive({ ...defaults, open: false, resolve: null });

export function confirmDialog(options = {}) {
  // Only one dialog at a time; a newer request cancels the older one.
  confirmState.resolve?.(false);

  return new Promise((resolve) => {
    Object.assign(confirmState, defaults, options, { open: true, resolve });
  });
}

export function settleConfirm(result) {
  const resolve = confirmState.resolve;
  confirmState.open = false;
  confirmState.resolve = null;
  resolve?.(result);
}
