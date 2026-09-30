<template>
  <div class="rich-editor" :class="{ 'is-focused': editor?.isFocused, 'has-error': error }">
    <!-- Toolbar -->
    <div class="editor-toolbar">
      <button type="button" @click="editor.chain().focus().toggleBold().run()"
              :class="{ active: editor?.isActive('bold') }" title="Bold (Ctrl+B)">
        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M15.6 10.79c.97-.67 1.65-1.77 1.65-2.79 0-2.26-1.75-4-4-4H8c-.55 0-1 .45-1 1v12c0 .55.45 1 1 1h5.78c2.07 0 3.96-1.69 3.97-3.77.01-1.53-.85-2.84-2.15-3.44zM10 6.5h3c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5h-3v-3zm3.5 9H10v-3h3.5c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5z"/></svg>
      </button>
      <button type="button" @click="editor.chain().focus().toggleItalic().run()"
              :class="{ active: editor?.isActive('italic') }" title="Italic (Ctrl+I)">
        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M10 4v3h2.21l-3.42 8H6v3h8v-3h-2.21l3.42-8H18V4z"/></svg>
      </button>
      <button type="button" @click="editor.chain().focus().toggleUnderline().run()"
              :class="{ active: editor?.isActive('underline') }" title="Underline (Ctrl+U)">
        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 17c3.31 0 6-2.69 6-6V3h-2.5v8c0 1.93-1.57 3.5-3.5 3.5S8.5 12.93 8.5 11V3H6v8c0 3.31 2.69 6 6 6zm-7 2v2h14v-2H5z"/></svg>
      </button>

      <span class="divider"></span>

      <button type="button" @click="editor.chain().focus().toggleSuperscript().run()"
              :class="{ active: editor?.isActive('superscript') }" title="Superscript (x²)">
        <span class="text-xs font-bold leading-none">x<sup>2</sup></span>
      </button>
      <button type="button" @click="editor.chain().focus().toggleSubscript().run()"
              :class="{ active: editor?.isActive('subscript') }" title="Subscript (x₂)">
        <span class="text-xs font-bold leading-none">x<sub>2</sub></span>
      </button>

      <span class="divider"></span>

      <button type="button" @click="editor.chain().focus().toggleBulletList().run()"
              :class="{ active: editor?.isActive('bulletList') }" title="Bullet list">
        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M4 10.5c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5zm0-6c-.83 0-1.5.67-1.5 1.5S3.17 7.5 4 7.5 5.5 6.83 5.5 6 4.83 4.5 4 4.5zm0 12c-.83 0-1.5.68-1.5 1.5s.68 1.5 1.5 1.5 1.5-.68 1.5-1.5-.67-1.5-1.5-1.5zM7 19h14v-2H7v2zm0-6h14v-2H7v2zm0-8v2h14V5H7z"/></svg>
      </button>

      <span class="divider"></span>

      <button v-if="allowImages" type="button" :disabled="uploadingImage" @click="imageInput?.click()" title="Insert image">
        <svg v-if="!uploadingImage" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="5" width="18" height="14" rx="2"/>
          <circle cx="8.5" cy="10" r="1.5"/>
          <path d="M21 15l-4.5-4.5L10 17l-2.5-2.5L3 19"/>
        </svg>
        <svg v-else class="w-3.5 h-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
          <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3"/>
          <path class="opacity-75" fill="currentColor" d="M12 3a9 9 0 00-9 9h3a6 6 0 016-6V3z"/>
        </svg>
      </button>
      <input
        v-if="allowImages"
        ref="imageInput"
        type="file"
        accept="image/png,image/jpeg,image/jpg,image/gif,image/webp"
        class="hidden"
        @change="uploadImage"
      />

      <button type="button" @click="editor.chain().focus().clearNodes().unsetAllMarks().run()" title="Clear formatting">
        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M6 16.5l3-2.94c-.39-.29-.77-.62-1.14-.99C6.13 10.84 5 8.55 5 6h2c0 2 .96 3.75 2.46 4.83L10.92 9h.01L14 5h-8l1.5-1.5h11.33L5.72 17zM19 6.41L17.59 5 13 9.59l1.41 1.41L19 6.41zM13.5 18c0 1.1-.9 2-2 2s-2-.9-2-2l2-4.94L13.5 18z"/></svg>
      </button>
    </div>

    <!-- Editor Content -->
    <EditorContent :editor="editor" class="editor-content" />

    <!-- Character count hint -->
    <div v-if="showCount && editor" class="editor-footer">
      {{ editor.storage.characterCount?.characters() ?? 0 }} characters
    </div>
    <p v-if="uploadError" class="editor-error">{{ uploadError }}</p>
  </div>
</template>

<script setup>
import { ref, watch, onBeforeUnmount } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import { Node, mergeAttributes } from '@tiptap/core';
import Underline from '@tiptap/extension-underline';
import Superscript from '@tiptap/extension-superscript';
import Subscript from '@tiptap/extension-subscript';
import Placeholder from '@tiptap/extension-placeholder';

const ImageNode = Node.create({
  name: 'image',
  group: 'block',
  atom: true,
  draggable: true,

  addAttributes() {
    return {
      src: { default: null },
      alt: { default: null },
      title: { default: null },
    };
  },

  parseHTML() {
    return [{ tag: 'img[src]' }];
  },

  renderHTML({ HTMLAttributes }) {
    return ['img', mergeAttributes(HTMLAttributes)];
  },

  addCommands() {
    return {
      setImage: (options) => ({ commands }) => commands.insertContent({
        type: this.name,
        attrs: options,
      }),
    };
  },
});

const props = defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: 'Type here…' },
  error:       { type: Boolean, default: false },
  showCount:   { type: Boolean, default: false },
  minHeight:   { type: String, default: '120px' },
  allowImages: { type: Boolean, default: false },
  imageUploadUrl: { type: String, default: '/admin/questions/editor-images' },
});

const emit = defineEmits(['update:modelValue']);
const imageInput = ref(null);
const uploadingImage = ref(false);
const uploadError = ref('');

const editor = useEditor({
  content: props.modelValue,
  extensions: [
    StarterKit.configure({ heading: false, codeBlock: false, code: false }),
    ImageNode,
    Underline,
    Superscript,
    Subscript,
    Placeholder.configure({ placeholder: props.placeholder }),
  ],
  editorProps: {
    attributes: {
      class: 'editor-area',
      style: `min-height: ${props.minHeight}`,
    },
  },
  onUpdate({ editor }) {
    const html = editor.getHTML();
    emit('update:modelValue', html === '<p></p>' ? '' : html);
  },
});

const uploadImage = async (event) => {
  const file = event.target.files?.[0];
  event.target.value = '';
  uploadError.value = '';

  if (!file) return;
  if (!file.type.startsWith('image/')) {
    uploadError.value = 'Please choose a valid image file.';
    return;
  }
  if (file.size > 2 * 1024 * 1024) {
    uploadError.value = 'Images must be 2MB or smaller.';
    return;
  }

  const payload = new FormData();
  payload.append('image', file);

  uploadingImage.value = true;
  try {
    const response = await window.axios.post(props.imageUploadUrl, payload, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    if (response.data?.url) {
      editor.value?.chain().focus().setImage({
        src: response.data.url,
        alt: file.name.replace(/\.[^.]+$/, ''),
      }).run();
    }
  } catch (error) {
    uploadError.value = error.response?.data?.message || 'Image upload failed. Please try again.';
  } finally {
    uploadingImage.value = false;
  }
};

// Sync external modelValue → editor (for edit page)
watch(() => props.modelValue, (val) => {
  if (editor.value && editor.value.getHTML() !== val) {
    editor.value.commands.setContent(val || '', false);
  }
});

onBeforeUnmount(() => editor.value?.destroy());
</script>

<style scoped>
.rich-editor {
  border: 1.5px solid #e5e7eb;
  border-radius: 12px;
  overflow: hidden;
  transition: border-color 0.15s;
  background: #fff;
}
.rich-editor.is-focused {
  border-color: #131C3D;
  box-shadow: 0 0 0 3px rgba(19,28,61,0.08);
}
.rich-editor.has-error {
  border-color: #DC2626;
}

.editor-toolbar {
  display: flex;
  align-items: center;
  gap: 2px;
  padding: 6px 10px;
  border-bottom: 1px solid #f3f4f6;
  background: #f9fafb;
  flex-wrap: wrap;
}
.editor-toolbar button {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 6px;
  color: #64748b;
  transition: all 0.12s;
  cursor: pointer;
  background: transparent;
  border: none;
}
.editor-toolbar button:hover {
  background: #e8edf5;
  color: #131C3D;
}
.editor-toolbar button.active {
  background: #dbeafe;
  color: #131C3D;
  font-weight: 700;
}
.editor-toolbar button:disabled {
  opacity: .55;
  cursor: wait;
}
.divider {
  width: 1px;
  height: 20px;
  background: #e5e7eb;
  margin: 0 4px;
}
.editor-footer {
  padding: 4px 12px;
  border-top: 1px solid #f3f4f6;
  font-size: 11px;
  color: #9ca3af;
  text-align: right;
}
.editor-error {
  padding: 6px 12px 8px;
  border-top: 1px solid #fee2e2;
  color: #DC2626;
  font-size: 11px;
  line-height: 1.4;
}
</style>

<style>
/* Global — editor prose styles */
.editor-area {
  padding: 12px 14px;
  outline: none;
  font-size: 14px;
  line-height: 1.65;
  color: #0A1024;
  font-family: 'Inter', sans-serif;
}
.editor-area p { margin: 0 0 4px; }
.editor-area p:last-child { margin-bottom: 0; }
.editor-area strong { font-weight: 700; }
.editor-area em { font-style: italic; }
.editor-area u { text-decoration: underline; }
.editor-area ul { padding-left: 18px; margin: 4px 0; }
.editor-area li { margin: 2px 0; }
.editor-area sup { font-size: 0.75em; vertical-align: super; }
.editor-area sub { font-size: 0.75em; vertical-align: sub; }
.editor-area img {
  display: block;
  max-width: 100%;
  max-height: 260px;
  object-fit: contain;
  margin: 8px 0;
  border: 1px solid #E7D9BE;
  border-radius: 10px;
  background: #FBF6EC;
}
.editor-area .is-editor-empty:first-child::before {
  content: attr(data-placeholder);
  float: left;
  color: #94a3b8;
  pointer-events: none;
  height: 0;
}
</style>
