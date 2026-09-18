import Sortable from 'sortablejs';

window.Sortable = Sortable;

document.addEventListener('livewire:navigated', initSortables);
document.addEventListener('livewire:initialized', () => {
    initSortables();

    Livewire.hook('morph.updated', () => {
        queueMicrotask(initSortables);
    });
});

function initSortables() {
    document.querySelectorAll('[data-sortable]').forEach((el) => {
        if (el._sortable) {
            return;
        }

        el._sortable = Sortable.create(el, {
            handle: '[data-drag-handle]',
            animation: 150,
            ghostClass: 'opacity-40',
            delay: 120,
            delayOnTouchOnly: true,
            onEnd(evt) {
                const ids = Array.from(el.querySelectorAll('[data-item-id]')).map(
                    (node) => Number(node.dataset.itemId)
                );
                const componentId = el.dataset.componentId;
                if (!componentId || !window.Livewire) {
                    return;
                }
                const component = Livewire.find(componentId);
                if (component) {
                    component.call('reorder', ids);
                }
            },
        });
    });
}
