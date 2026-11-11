import { ref } from 'vue';

/**
 * Minimal native drag-to-reorder for a reactive array. Rows only become draggable while
 * their handle is pressed, so text inputs inside a row keep normal selection behaviour.
 *
 * @param {() => Array} getList  returns the (reactive) array to reorder in place
 * @param {() => void} [onChange] called after every move
 */
export function useDragSort(getList, onChange) {
    const armed = ref(null);
    const dragging = ref(null);
    const over = ref(null);

    const move = (from, to) => {
        const list = getList();
        if (from === null || to === null || from === to || to < 0 || to >= list.length) return;
        const [item] = list.splice(from, 1);
        list.splice(to, 0, item);
        onChange?.();
    };

    const reset = () => { armed.value = null; dragging.value = null; over.value = null; };

    const handle = (i) => ({
        onPointerdown: () => { armed.value = i; },
        onPointerup: () => { if (dragging.value === null) armed.value = null; },
    });

    const row = (i) => ({
        draggable: armed.value === i,
        onDragstart: (e) => {
            dragging.value = i;
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', String(i));
        },
        onDragover: (e) => {
            if (dragging.value === null) return;
            e.preventDefault();
            over.value = i;
        },
        onDrop: (e) => {
            e.preventDefault();
            move(dragging.value, i);
            reset();
        },
        onDragend: reset,
    });

    return { armed, dragging, over, move, handle, row };
}
