/**
 * Shared helpers for Admin → Notifications (workflow.md §13.2).
 * Labels and colours here must match NotificationLog::STATUSES / NotificationDelivery::STATUSES.
 */

export const CHANNELS = {
  email: { label: 'Email', short: 'Email', cls: 'bg-royal/10 text-royal' },
  in_app: { label: 'In-App', short: 'In-App', cls: 'bg-primary/10 text-primary' },
  whatsapp: { label: 'WhatsApp', short: 'WhatsApp', cls: 'bg-success/10 text-success' },
  both: { label: 'Email + In-App', short: 'Legacy', cls: 'bg-gray-100 text-text-muted' },
};

export const LOG_STATUS = {
  queued: { label: 'Queued', cls: 'bg-gold/15 text-gold-dark' },
  sending: { label: 'Sending', cls: 'bg-accent/10 text-accent-dark' },
  completed: { label: 'Completed', cls: 'bg-success/10 text-success' },
  partial: { label: 'Partially failed', cls: 'bg-danger/10 text-danger' },
  failed: { label: 'Failed', cls: 'bg-danger/10 text-danger' },
};

export const DELIVERY_STATUS = {
  pending: { label: 'Pending', cls: 'bg-gray-100 text-text-muted' },
  sending: { label: 'Sending', cls: 'bg-accent/10 text-accent-dark' },
  sent: { label: 'Sent', cls: 'bg-royal/10 text-royal' },
  delivered: { label: 'Delivered', cls: 'bg-success/10 text-success' },
  read: { label: 'Read', cls: 'bg-success/15 text-success' },
  failed: { label: 'Failed', cls: 'bg-danger/10 text-danger' },
  skipped: { label: 'Skipped', cls: 'bg-gray-100 text-text-muted' },
};

export const defaultAudience = () => ({
  mode: 'filters',
  student_status: 'active',
  class_level_ids: [],
  subject_ids: [],
  exam_ids: [],
  enrollment_status: 'all',
  attempt_status: 'all',
  payment_status: 'all',
  hall_ticket: 'all',
  registration_source: 'all',
  state: '',
  city: '',
  school: '',
  search: '',
  excluded_ids: [],
  selected_ids: [],
});

export const newKey = () =>
  (window.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(16).slice(2)}-${Math.random().toString(16).slice(2)}`);

/** First validation message from an axios 422, or a readable fallback. */
export const errorText = (error, fallback = 'Something went wrong. Please try again.') => {
  const data = error?.response?.data;
  if (data?.errors) {
    const first = Object.values(data.errors).flat()[0];
    if (first) return first;
  }
  if (error?.response?.status === 419) return 'Your session expired. Reload the page and try again.';
  if (error?.response?.status === 403) return 'You don’t have permission to do this.';
  return data?.message || fallback;
};

/** Field → message map from an axios 422 (first message per field). */
export const fieldErrors = (error) => {
  const errors = error?.response?.data?.errors ?? {};
  return Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]));
};

export const plural = (n, one, many = `${one}s`) => `${Number(n || 0).toLocaleString('en-IN')} ${n === 1 ? one : many}`;

/** Inserts text at the cursor of an <input>/<textarea> bound with v-model. */
export const insertAtCursor = (el, current, text) => {
  if (!el) return `${current || ''}${text}`;
  const start = el.selectionStart ?? (current || '').length;
  const end = el.selectionEnd ?? start;
  const value = `${(current || '').slice(0, start)}${text}${(current || '').slice(end)}`;
  requestAnimationFrame(() => {
    el.focus();
    el.setSelectionRange(start + text.length, start + text.length);
  });
  return value;
};
