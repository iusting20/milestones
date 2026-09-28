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
    document.querySelectorAll('._sortable, [data-sortable]').forEach((el) => {
        if (el._sortable && el.dataset.sortable === undefined) {
            el._sortable.destroy();
            delete el._sortable;
        }
    });

    document.querySelectorAll('[data-sortable]').forEach((el) => {
        if (el._sortable) {
            return;
        }

        el._sortable = Sortable.create(el, {
            group: el.dataset.componentId || 'todos',
            handle: '[data-drag-handle]',
            filter: '[data-drop-slot]',
            preventOnFilter: false,
            animation: 150,
            ghostClass: 'opacity-40',
            fallbackOnBody: true,
            swapThreshold: 0.65,
            emptyInsertThreshold: 48,
            delay: 120,
            delayOnTouchOnly: true,
            onMove(evt) {
                document.querySelectorAll('.nest-target').forEach((node) => {
                    node.classList.remove('nest-target');
                });

                if (evt.dragged.contains(evt.to)) {
                    return false;
                }

                if (evt.to.dataset.parentId && evt.dragged.querySelector('[data-item-id]')) {
                    return false;
                }

                if (evt.to.dataset.parentId) {
                    evt.to.classList.add('nest-target');
                }
            },
            onEnd(evt) {
                document.querySelectorAll('.nest-target').forEach((node) => {
                    node.classList.remove('nest-target');
                });

                syncSortableList(evt.to);

                if (evt.from !== evt.to) {
                    syncSortableList(evt.from);
                }
            },
        });
    });
}

function syncSortableList(list) {
    const componentId = list.dataset.componentId;

    if (!componentId || !window.Livewire) {
        return;
    }

    const component = Livewire.find(componentId);

    if (!component) {
        return;
    }

    const ids = Array.from(list.children)
        .filter((node) => node.matches('[data-item-id]'))
        .map((node) => Number(node.dataset.itemId));

    if (list.dataset.sortableNest !== undefined) {
        const parentId = list.dataset.parentId ? Number(list.dataset.parentId) : null;
        component.call('reorder', ids, parentId);

        return;
    }

    component.call('reorder', ids);
}
