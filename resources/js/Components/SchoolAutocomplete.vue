<script setup>
import { ref, watch, onBeforeUnmount } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },   // school name
    address:    { type: String, default: '' },    // school address (auto-filled)
    id:         { type: String, default: 'school' },
    placeholder:{ type: String, default: 'Start typing your school name…' },
    searchRouteName: { type: String, default: 'schools.search' },
    searchParams: { type: Object, default: () => ({}) },
    variant: { type: String, default: 'public' },
});

const emit = defineEmits(['update:modelValue', 'update:address', 'selected']);

const query = ref(props.modelValue ?? '');
const suggestions = ref([]);
const open = ref(false);
const active = ref(-1);      // highlighted index for keyboard nav
const loading = ref(false);
const root = ref(null);

let debounceId = null;
let requestSeq = 0;          // guards against out-of-order responses

// Keep the input in sync if the parent resets the value externally.
watch(() => props.modelValue, (v) => { if (v !== query.value) query.value = v ?? ''; });

const fetchSuggestions = async (term) => {
    const seq = ++requestSeq;
    loading.value = true;
    try {
        const { data } = await window.axios.get(route(props.searchRouteName), {
            params: { ...props.searchParams, q: term },
        });
        if (seq !== requestSeq) return;               // a newer request superseded this one
        suggestions.value = data?.data ?? [];
        active.value = -1;
        open.value = suggestions.value.length > 0;
    } catch {
        if (seq === requestSeq) { suggestions.value = []; open.value = false; }
    } finally {
        if (seq === requestSeq) loading.value = false;
    }
};

const onInput = () => {
    const val = query.value;
    // Typing a name detaches it from any previously selected school → clear address.
    emit('update:modelValue', val);
    emit('update:address', '');

    clearTimeout(debounceId);
    const term = val.trim();
    if (term.length < 2) { suggestions.value = []; open.value = false; return; }
    debounceId = setTimeout(() => fetchSuggestions(term), 250);
};

const select = (school) => {
    query.value = school.name;
    emit('update:modelValue', school.name);
    emit('update:address', school.address ?? '');
    emit('selected', school);
    open.value = false;
    active.value = -1;
};

const onKeydown = (e) => {
    if (!open.value || !suggestions.value.length) return;
    if (e.key === 'ArrowDown') { e.preventDefault(); active.value = (active.value + 1) % suggestions.value.length; }
    else if (e.key === 'ArrowUp') { e.preventDefault(); active.value = (active.value - 1 + suggestions.value.length) % suggestions.value.length; }
    else if (e.key === 'Enter') {
        if (active.value >= 0) { e.preventDefault(); select(suggestions.value[active.value]); }
    } else if (e.key === 'Escape') { open.value = false; active.value = -1; }
};

const onFocus = () => { if (suggestions.value.length) open.value = true; };

const onDocClick = (e) => { if (root.value && !root.value.contains(e.target)) open.value = false; };
document.addEventListener('click', onDocClick);
onBeforeUnmount(() => { document.removeEventListener('click', onDocClick); clearTimeout(debounceId); });
</script>

<template>
    <div ref="root" class="sa" :class="`sa-${variant}`">
        <div class="control" :class="{ open }">
            <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 21h18M5 21V8l7-5 7 5v13M9 21v-6h6v6"/></svg></span>
            <input
                :id="id"
                type="text"
                v-model="query"
                :placeholder="placeholder"
                autocomplete="off"
                role="combobox"
                aria-autocomplete="list"
                :aria-expanded="open"
                @input="onInput"
                @keydown="onKeydown"
                @focus="onFocus"
            />
            <span v-if="loading" class="spin" aria-hidden="true"></span>
        </div>

        <ul v-if="open" class="menu" role="listbox">
            <li
                v-for="(s, i) in suggestions"
                :key="s.id"
                role="option"
                :aria-selected="i === active"
                :class="{ active: i === active }"
                @mousedown.prevent="select(s)"
                @mouseenter="active = i"
            >
                <strong>{{ s.name }}</strong>
                <small v-if="s.address">{{ s.address }}</small>
            </li>
        </ul>
    </div>
</template>

<style scoped>
.sa { position: relative; }

.control {
    position: relative; display: flex; align-items: center;
    background: #fff; border: 1.5px solid #E7D9BE; border-radius: 14px;
    transition: border-color .2s, box-shadow .2s;
}
.control:focus-within { border-color: #EE6A2C; box-shadow: 0 0 0 4px rgba(238,106,44,.12); }
.control.open { border-bottom-left-radius: 0; border-bottom-right-radius: 0; }
.control .ic { display: grid; place-items: center; width: 42px; color: rgba(10,16,36,.4); flex-shrink: 0; }
.control .ic svg { width: 17px; height: 17px; }
.control input {
    flex: 1; min-width: 0; border: 0; background: transparent; outline: none;
    padding: .75rem .8rem .75rem 0; font-size: .94rem; color: #0A1024;
    font-family: "Plus Jakarta Sans", sans-serif;
}
.control input::placeholder { color: rgba(10,16,36,.32); }

.spin { width: 15px; height: 15px; margin-right: .8rem; flex-shrink: 0;
    border: 2px solid rgba(238,106,44,.25); border-top-color: #EE6A2C; border-radius: 50%;
    animation: sa-spin .7s linear infinite; }
@keyframes sa-spin { to { transform: rotate(360deg); } }

.menu {
    position: absolute; z-index: 30; top: 100%; left: 0; right: 0; margin: 0; padding: .3rem;
    list-style: none; max-height: 280px; overflow-y: auto;
    background: #fff; border: 1.5px solid #EE6A2C; border-top: 0;
    border-radius: 0 0 14px 14px; box-shadow: 0 20px 40px -18px rgba(10,16,36,.35);
}
.menu li { padding: .55rem .7rem; border-radius: 10px; cursor: pointer; display: grid; gap: .1rem; }
.menu li.active { background: rgba(238,106,44,.1); }
.menu li strong { font-size: .9rem; font-weight: 600; color: #0A1024; }
.menu li small { font-size: .76rem; color: rgba(10,16,36,.5); line-height: 1.3;
    overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; }

.sa-admin .control {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: .75rem;
}
.sa-admin .control:focus-within {
    border-color: #131C3D;
    box-shadow: 0 0 0 2px rgba(19,28,61,.1);
}
.sa-admin .control.open {
    border-bottom-left-radius: 0;
    border-bottom-right-radius: 0;
}
.sa-admin .control input {
    min-height: 2.5rem;
    padding: .625rem .75rem .625rem 0;
    font-size: .8125rem;
    font-family: Inter, system-ui, sans-serif;
}
.sa-admin .menu {
    border: 1px solid #131C3D;
    border-top: 0;
    border-radius: 0 0 .75rem .75rem;
}
.sa-admin .menu li.active {
    background: rgba(19,28,61,.08);
}
.sa-admin .spin {
    border-color: rgba(19,28,61,.2);
    border-top-color: #131C3D;
}
</style>
