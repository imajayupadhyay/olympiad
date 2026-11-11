<script setup>
/**
 * Admin-defined student fields. Changes save immediately (they change the student
 * profile form), independent of the layout draft.
 */
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { confirmDialog } from '@/composables/useConfirm';
import ToggleSwitch from './ToggleSwitch.vue';
import { useDragSort } from './useDragSort';

const props = defineProps({
    fields: { type: Array, required: true },
    types: { type: Object, required: true },
    canWrite: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
});

const list = ref(props.fields.map((f) => ({ ...f })));
watch(() => props.fields, (fields) => { list.value = fields.map((f) => ({ ...f })); });

const reload = { preserveScroll: true, preserveState: true, only: ['customFields', 'catalogue', 'flash'] };

const persistOrder = () => router.post(route('admin.settings.hall-ticket.fields.reorder'), { ids: list.value.map((f) => f.id) }, reload);
const { over, dragging, move, handle, row } = useDragSort(() => list.value, persistOrder);

/* add / edit modal */
const open = ref(false);
const editing = ref(null);
const optionDraft = ref('');
const form = useForm({ label: '', type: 'text', options: [], placeholder: '', help_text: '', is_required: false, is_active: true });

const startAdd = () => {
    editing.value = null;
    form.reset();
    form.clearErrors();
    optionDraft.value = '';
    open.value = true;
};
const startEdit = (f) => {
    editing.value = f;
    form.clearErrors();
    Object.assign(form, { label: f.label, type: f.type, options: [...(f.options ?? [])], placeholder: f.placeholder ?? '', help_text: f.help_text ?? '', is_required: f.is_required, is_active: f.is_active });
    optionDraft.value = '';
    open.value = true;
};
const addOption = () => {
    const v = optionDraft.value.trim();
    if (v && !form.options.includes(v)) form.options.push(v);
    optionDraft.value = '';
};
const submit = () => {
    const opts = { ...reload, onSuccess: () => { open.value = false; } };
    if (editing.value) form.put(route('admin.settings.hall-ticket.fields.update', editing.value.id), opts);
    else form.post(route('admin.settings.hall-ticket.fields.store'), opts);
};

const quickToggle = (f, key) => {
    router.put(route('admin.settings.hall-ticket.fields.update', f.id), {
        label: f.label, options: f.options, placeholder: f.placeholder, help_text: f.help_text,
        is_required: f.is_required, is_active: f.is_active, [key]: !f[key],
    }, reload);
};

const remove = async (f) => {
    if (!(await confirmDialog({
        title: `Delete “${f.label}”?`,
        message: f.filled_count
            ? `${f.filled_count} student${f.filled_count === 1 ? ' has' : 's have'} filled this in. Their answers are deleted too and the field disappears from the profile and every hall ticket. To keep the data, switch the field off instead.`
            : 'The field disappears from the student profile and every hall ticket.',
        confirmText: 'Delete field',
        tone: 'danger',
    }))) return;
    router.delete(route('admin.settings.hall-ticket.fields.destroy', f.id), reload);
};

const typeLabel = (t) => props.types[t] ?? t;
const canSubmit = computed(() => form.label.trim() && (form.type !== 'select' || form.options.length));
</script>

<template>
    <div>
        <div class="rounded-xl bg-blue-50 border border-blue-100 text-xs text-royal px-3 py-2.5 mb-3 leading-relaxed">
            Fields added here appear on every student's profile under <b>Additional details</b>. <b>Required</b> fields count toward the
            90% completion rule and must be filled before a student can submit. To print a field on the hall ticket, switch it on under <b>Ticket fields</b>.
        </div>

        <ul class="space-y-1.5">
            <li v-for="(f, i) in list" :key="f.id" v-bind="canWrite ? row(i) : {}"
                class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-xl border px-3 py-2.5 bg-white transition-colors"
                :class="[over === i && dragging !== i ? 'border-accent ring-2 ring-accent/20' : 'border-gray-200', dragging === i ? 'opacity-50' : '', !f.is_active ? 'bg-gray-50' : '']">
                <button v-if="canWrite" type="button" v-bind="handle(i)" class="cursor-grab active:cursor-grabbing text-gray-400 hover:text-text-main -ml-1 touch-none" aria-label="Drag to reorder">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="6" r="1.6"/><circle cx="15" cy="6" r="1.6"/><circle cx="9" cy="12" r="1.6"/><circle cx="15" cy="12" r="1.6"/><circle cx="9" cy="18" r="1.6"/><circle cx="15" cy="18" r="1.6"/></svg>
                </button>
                <div class="min-w-0 flex-1 basis-40">
                    <p class="text-sm font-semibold" :class="f.is_active ? 'text-text-main' : 'text-text-muted line-through'">{{ f.label }}</p>
                    <p class="text-[11px] text-text-muted">
                        {{ typeLabel(f.type) }}<template v-if="f.type === 'select'">: {{ f.options.join(', ') }}</template>
                        · filled by <b class="font-number">{{ f.filled_count }}</b> student{{ f.filled_count === 1 ? '' : 's' }}
                    </p>
                </div>
                <label class="flex items-center gap-1.5 text-[11px] font-semibold text-text-muted">
                    <ToggleSwitch :model-value="f.is_required" :disabled="!canWrite" size="sm" label="Required" @update:model-value="quickToggle(f, 'is_required')" /> Required
                </label>
                <label class="flex items-center gap-1.5 text-[11px] font-semibold text-text-muted">
                    <ToggleSwitch :model-value="f.is_active" :disabled="!canWrite" size="sm" label="Active" @update:model-value="quickToggle(f, 'is_active')" /> Active
                </label>
                <div v-if="canWrite" class="flex gap-1">
                    <button type="button" class="act" @click="startEdit(f)">Edit</button>
                    <button v-if="canDelete" type="button" class="act text-danger" @click="remove(f)">Delete</button>
                </div>
            </li>
        </ul>
        <p v-if="!list.length" class="rounded-xl border border-dashed border-gray-200 py-6 text-center text-sm text-text-muted">No custom student fields.</p>
        <button v-if="canWrite" type="button" class="btn-add mt-3" @click="startAdd">+ Add student field</button>

        <!-- add / edit modal -->
        <Teleport to="body">
            <div v-if="open" class="fixed inset-0 z-50 bg-black/50 grid place-items-center p-4" @click.self="open = false">
                <form class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-5 max-h-[90vh] overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="cf-title" @submit.prevent="submit">
                    <h3 id="cf-title" class="font-heading font-bold text-lg text-text-main">{{ editing ? `Edit “${editing.label}”` : 'Add a student field' }}</h3>
                    <p class="text-sm text-text-muted mt-1">Students see this on their profile and fill it in themselves.</p>

                    <div class="grid gap-3 mt-4">
                        <label class="blk"><span class="lbl">Label</span>
                            <input v-model="form.label" type="text" maxlength="80" class="field" placeholder="e.g. Father's name" autofocus />
                            <span v-if="form.errors.label" class="err">{{ form.errors.label }}</span>
                        </label>
                        <div>
                            <span class="lbl">Type</span>
                            <div class="grid grid-cols-3 gap-1 rounded-xl border border-gray-200 p-1">
                                <button v-for="(label, key) in types" :key="key" type="button" :disabled="!!editing"
                                        class="rounded-lg py-2 text-sm font-semibold transition-colors disabled:cursor-not-allowed"
                                        :class="form.type === key ? 'bg-primary text-white' : 'text-text-muted hover:bg-gray-50'" @click="form.type = key">{{ label }}</button>
                            </div>
                            <p v-if="editing" class="hint">The type can't change once created (it would break students' saved answers).</p>
                        </div>
                        <div v-if="form.type === 'select'">
                            <span class="lbl">Choices</span>
                            <div class="flex flex-wrap gap-1.5 mb-2">
                                <span v-for="(o, i) in form.options" :key="o" class="inline-flex items-center gap-1 text-xs font-semibold bg-gray-100 rounded-lg pl-2.5 pr-1 py-1">
                                    {{ o }} <button type="button" class="w-5 h-5 rounded hover:bg-gray-200" :aria-label="`Remove ${o}`" @click="form.options.splice(i, 1)">×</button>
                                </span>
                            </div>
                            <div class="flex gap-2">
                                <input v-model="optionDraft" type="text" maxlength="60" class="field" placeholder="Type a choice and press Enter" @keydown.enter.prevent="addOption" />
                                <button type="button" class="act border border-gray-200" @click="addOption">Add</button>
                            </div>
                            <span v-if="form.errors.options" class="err">{{ form.errors.options }}</span>
                        </div>
                        <label v-if="form.type !== 'select'" class="blk"><span class="lbl">Placeholder (optional)</span>
                            <input v-model="form.placeholder" type="text" maxlength="120" class="field" placeholder="Hint shown inside the empty box" />
                        </label>
                        <label class="blk"><span class="lbl">Help text (optional)</span>
                            <input v-model="form.help_text" type="text" maxlength="200" class="field" placeholder="Shown under the field" />
                        </label>
                        <div class="flex flex-wrap gap-5">
                            <label class="flex items-center gap-2 text-sm"><ToggleSwitch v-model="form.is_required" size="sm" /> Required for the hall ticket</label>
                            <label class="flex items-center gap-2 text-sm"><ToggleSwitch v-model="form.is_active" size="sm" /> Active</label>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 mt-5">
                        <button type="button" class="px-4 py-2.5 rounded-xl text-sm font-semibold text-text-main hover:bg-gray-100" @click="open = false">Cancel</button>
                        <button type="submit" class="px-4 py-2.5 rounded-xl text-sm font-semibold bg-primary text-white hover:bg-primary-light disabled:opacity-50" :disabled="form.processing || !canSubmit">
                            {{ form.processing ? 'Saving…' : editing ? 'Save field' : 'Add field' }}
                        </button>
                    </div>
                </form>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.lbl { @apply block text-[11px] font-semibold uppercase tracking-wider text-text-muted mb-1.5; }
.blk { @apply block; }
.field { @apply w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-gray-50 text-text-main; }
.err { @apply block text-xs text-danger mt-1; }
.hint { @apply text-[11px] text-text-muted mt-1; }
.act { @apply text-xs font-semibold px-2.5 py-1.5 rounded-lg hover:bg-gray-100 transition-colors; }
.btn-add { @apply text-sm font-semibold px-3.5 py-2 rounded-xl border border-dashed border-primary/40 text-primary hover:bg-primary/5; }
</style>
