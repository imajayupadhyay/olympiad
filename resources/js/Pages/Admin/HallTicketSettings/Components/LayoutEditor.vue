<script setup>
import ToggleSwitch from './ToggleSwitch.vue';

const layout = defineModel({ type: Object, required: true });
defineProps({ disabled: { type: Boolean, default: false } });

const choice = (key, value) => { layout.value[key] = value; };
</script>

<template>
    <div class="space-y-5">
        <!-- look -->
        <section class="group-box">
            <h4 class="group-h">Look &amp; header</h4>
            <div class="grid sm:grid-cols-2 gap-3">
                <div class="sm:col-span-2">
                    <span class="lbl">Style</span>
                    <div class="seg">
                        <button v-for="o in [['brand', 'Brand colours', 'Ink header with saffron accents'], ['classic', 'Classic black & white', 'Best for photocopies and mono printers']]" :key="o[0]"
                                type="button" :disabled="disabled" class="seg-btn" :class="{ on: layout.theme === o[0] }" @click="choice('theme', o[0])">
                            <b>{{ o[1] }}</b><small>{{ o[2] }}</small>
                        </button>
                    </div>
                </div>
                <label class="blk"><span class="lbl">Title</span><input v-model="layout.title" type="text" maxlength="40" :disabled="disabled" class="field" /></label>
                <label class="blk"><span class="lbl">Subtitle</span><input v-model="layout.subtitle" type="text" maxlength="90" :disabled="disabled" class="field" /></label>
            </div>
            <div class="toggles">
                <label class="tg"><ToggleSwitch v-model="layout.show_logo" :disabled="disabled" size="sm" /> Logo</label>
                <label class="tg"><ToggleSwitch v-model="layout.show_session" :disabled="disabled" size="sm" /> Academic session</label>
                <label class="tg"><ToggleSwitch v-model="layout.show_roll_box" :disabled="disabled" size="sm" /> Roll number box</label>
                <label class="tg"><ToggleSwitch v-model="layout.show_watermark" :disabled="disabled" size="sm" /> "Not approved" watermark</label>
            </div>
        </section>

        <!-- candidate block -->
        <section class="group-box">
            <h4 class="group-h">Candidate details</h4>
            <div class="grid sm:grid-cols-2 gap-3">
                <div>
                    <span class="lbl">Columns</span>
                    <div class="seg">
                        <button v-for="n in [1, 2, 3]" :key="n" type="button" :disabled="disabled" class="seg-btn !py-2" :class="{ on: layout.detail_columns === n }" @click="choice('detail_columns', n)"><b>{{ n }}</b></button>
                    </div>
                </div>
                <div>
                    <span class="lbl">Student photograph</span>
                    <div class="seg">
                        <button v-for="o in [['left', 'Left'], ['right', 'Right'], ['hidden', 'Off']]" :key="o[0]" type="button" :disabled="disabled" class="seg-btn !py-2" :class="{ on: layout.photo === o[0] }" @click="choice('photo', o[0])"><b>{{ o[1] }}</b></button>
                    </div>
                </div>
            </div>
            <div class="toggles">
                <label class="tg"><ToggleSwitch v-model="layout.show_signature_box" :disabled="disabled || layout.photo === 'hidden'" size="sm" /> Signature box under photo</label>
                <label class="tg"><ToggleSwitch v-model="layout.hide_empty" :disabled="disabled" size="sm" /> Hide empty fields (instead of "—")</label>
            </div>
        </section>

        <!-- schedule -->
        <section class="group-box">
            <div class="flex items-center justify-between">
                <h4 class="group-h !mb-0">Examination schedule</h4>
                <ToggleSwitch v-model="layout.show_schedule" :disabled="disabled" label="Show schedule" />
            </div>
            <div v-if="layout.show_schedule" class="grid sm:grid-cols-2 gap-3 mt-3">
                <label class="blk"><span class="lbl">Section title</span><input v-model="layout.schedule_title" type="text" maxlength="50" :disabled="disabled" class="field" /></label>
                <label class="blk"><span class="lbl">Log in by (minutes before start)</span><input v-model.number="layout.login_offset_minutes" type="number" min="0" max="120" :disabled="disabled" class="field" /></label>
                <label class="blk"><span class="lbl">Mode (column text)</span><input v-model="layout.mode_text" type="text" maxlength="30" :disabled="disabled" class="field" /></label>
                <label class="blk"><span class="lbl">Examination centre</span><input v-model="layout.exam_centre" type="text" maxlength="120" :disabled="disabled" class="field" /></label>
                <label class="tg sm:col-span-2"><ToggleSwitch v-model="layout.exam_detail_line" :disabled="disabled" size="sm" /> Subject · code line under the olympiad name</label>
            </div>
            <div class="mt-3">
                <label class="tg"><ToggleSwitch v-model="layout.show_venue" :disabled="disabled" size="sm" /> Venue line</label>
                <input v-if="layout.show_venue" v-model="layout.venue_text" type="text" maxlength="200" :disabled="disabled" class="field mt-2" />
                <p v-if="layout.show_venue" class="hint">Use <code>{portal}</code> for the website address.</p>
            </div>
        </section>

        <!-- instructions -->
        <section class="group-box">
            <div class="flex items-center justify-between">
                <h4 class="group-h !mb-0">Instructions section</h4>
                <ToggleSwitch v-model="layout.show_instructions" :disabled="disabled" label="Show instructions" />
            </div>
            <div v-if="layout.show_instructions" class="grid sm:grid-cols-2 gap-3 mt-3">
                <label class="blk"><span class="lbl">Section title</span><input v-model="layout.instructions_title" type="text" maxlength="60" :disabled="disabled" class="field" /></label>
                <div>
                    <span class="lbl">Columns</span>
                    <div class="seg">
                        <button v-for="n in [1, 2]" :key="n" type="button" :disabled="disabled" class="seg-btn !py-2" :class="{ on: layout.instruction_columns === n }" @click="choice('instruction_columns', n)"><b>{{ n }}</b></button>
                    </div>
                </div>
            </div>
        </section>

        <!-- signatures -->
        <section class="group-box">
            <div class="flex items-center justify-between">
                <h4 class="group-h !mb-0">Signatures &amp; stamp</h4>
                <ToggleSwitch v-model="layout.show_signatures" :disabled="disabled" label="Show signature lines" />
            </div>
            <div v-if="layout.show_signatures" class="grid sm:grid-cols-3 gap-3 mt-3">
                <label v-for="i in 3" :key="i" class="blk">
                    <span class="lbl">Signature {{ i }}</span>
                    <input v-model="layout.signatures[i - 1]" type="text" maxlength="40" :disabled="disabled" class="field" placeholder="Leave blank to remove" />
                </label>
            </div>
            <div class="grid sm:grid-cols-2 gap-3 mt-3 items-end">
                <label class="tg"><ToggleSwitch v-model="layout.show_stamp" :disabled="disabled" size="sm" /> Approval stamp on approved tickets</label>
                <input v-if="layout.show_stamp" v-model="layout.stamp_text" type="text" maxlength="30" :disabled="disabled" class="field" aria-label="Stamp text" />
            </div>
        </section>

        <section class="group-box">
            <h4 class="group-h">Footer</h4>
            <input v-model="layout.footer_note" type="text" maxlength="200" :disabled="disabled" class="field" placeholder="Small note at the bottom (blank to remove)" />
        </section>
    </div>
</template>

<style scoped>
.group-box { @apply rounded-2xl border border-gray-100 bg-gray-50/60 p-4; }
.group-h { @apply font-heading font-bold text-sm text-text-main mb-3; }
.lbl { @apply block text-[11px] font-semibold uppercase tracking-wider text-text-muted mb-1.5; }
.blk { @apply block; }
.field { @apply w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-primary bg-white text-text-main disabled:opacity-60; }
.toggles { @apply flex flex-wrap gap-x-5 gap-y-2 mt-3; }
.tg { @apply flex items-center gap-2 text-sm text-text-main cursor-pointer select-none; }
.seg { @apply grid grid-flow-col auto-cols-fr gap-1 rounded-xl bg-white border border-gray-200 p-1; }
.seg-btn { @apply rounded-lg px-3 py-2.5 text-left text-sm text-text-muted transition-colors hover:bg-gray-50 disabled:opacity-60; }
.seg-btn b { @apply block text-text-main font-semibold; }
.seg-btn small { @apply block text-[11px] leading-tight mt-0.5; }
.seg-btn.on { @apply bg-primary text-white/80; }
.seg-btn.on b { @apply text-white; }
.hint { @apply text-[11px] text-text-muted mt-1; }
.hint code { @apply bg-white border border-gray-200 rounded px-1; }
</style>
