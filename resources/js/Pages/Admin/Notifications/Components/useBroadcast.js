import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { confirmDialog } from '@/composables/useConfirm';
import { CHANNELS, errorText, fieldErrors, newKey, plural } from './notifications';

/**
 * Preview, test and send for one channel composer. The server renders every preview
 * (same code path as delivery), so what the admin sees is what students receive.
 *
 * @param {object} o
 * @param {string} o.channel            'email' | 'in_app' | 'whatsapp'
 * @param {() => object|null} o.content returns the channel payload, or null when incomplete
 * @param {import('vue').Ref} o.audience
 * @param {import('vue').Ref} o.examId
 * @param {import('vue').Ref} o.summary
 */
export function useBroadcast({ channel, content, audience, examId, summary }) {
  const preview = ref(null);
  const previewLoading = ref(false);
  const previewError = ref('');
  const errors = ref({});
  const sending = ref(false);
  const testing = ref(false);
  const testResult = ref(null);
  let key = newKey();
  let timer = null;
  let seq = 0;

  const base = () => ({
    channel,
    audience: audience.value,
    exam_context_id: examId.value || null,
    [channel]: content(),
  });

  const loadPreview = async () => {
    const body = content();
    if (!body) {
      preview.value = null;
      return;
    }
    const mine = ++seq;
    previewLoading.value = true;
    previewError.value = '';
    try {
      const { data } = await window.axios.post(route('admin.notifications.preview'), base());
      if (mine !== seq) return;
      preview.value = data;
    } catch (e) {
      if (mine !== seq) return;
      previewError.value = errorText(e, 'Could not render the preview.');
    } finally {
      if (mine === seq) previewLoading.value = false;
    }
  };

  const schedulePreview = (delay = 450) => {
    clearTimeout(timer);
    timer = setTimeout(loadPreview, delay);
  };

  watch(() => JSON.stringify([content(), examId.value, audience.value]), () => schedulePreview(), { immediate: true });
  watch(() => summary.value?.recipients, (n, o) => { if (!o && n) schedulePreview(100); });

  const sendTest = async (to) => {
    testing.value = true;
    testResult.value = null;
    try {
      const { data } = await window.axios.post(route('admin.notifications.test'), {
        channel,
        to,
        exam_context_id: examId.value || null,
        sample_user_id: preview.value?.sample?.id ?? null,
        [channel]: content(),
      });
      testResult.value = { ok: true, message: data.message };
    } catch (e) {
      testResult.value = { ok: false, message: errorText(e, 'The test could not be sent.') };
    } finally {
      testing.value = false;
    }
  };

  const post = (allowDuplicate, onSent) => {
    sending.value = true;
    router.post(route('admin.notifications.send'), { ...base(), idempotency_key: key, allow_duplicate: allowDuplicate }, {
      preserveScroll: true,
      onSuccess: () => {
        key = newKey();
        errors.value = {};
        onSent?.();
      },
      onError: async (errs) => {
        errors.value = errs;
        if (errs.duplicate && !allowDuplicate) {
          const again = await confirmDialog({
            title: 'Send this again?',
            message: errs.duplicate,
            confirmText: 'Send again anyway',
            cancelText: 'Don’t send',
            tone: 'warning',
          });
          if (again) post(true, onSent);
        }
      },
      onFinish: () => { sending.value = false; },
    });
  };

  const send = async (onSent) => {
    const n = summary.value?.recipients ?? 0;
    if (!n || !content()) return;

    const ok = await confirmDialog({
      title: `Send ${CHANNELS[channel].label} to ${plural(n, 'student')}?`,
      message: channel === 'in_app'
        ? 'It appears in each student’s notification bell immediately. You can delete it later from History.'
        : 'Messages start going out right away and can’t be recalled. You can follow delivery on the next screen.',
      confirmText: `Send to ${n.toLocaleString('en-IN')}`,
      tone: 'primary',
    });
    if (ok) post(false, onSent);
  };

  return { preview, previewLoading, previewError, errors, sending, testing, testResult, loadPreview, schedulePreview, sendTest, send, fieldErrors };
}
