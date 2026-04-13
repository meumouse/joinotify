import { computed, ref, unref, watch } from 'vue';

function resolveItems(source) {
  if (typeof source === 'function') {
    return source() || [];
  }

  return unref(source) || [];
}

function normalizeId(id) {
  return String(id);
}

export function useBulkSelection(sourceItems) {
  const selectedIds = ref([]);

  const visibleItems = computed(() => resolveItems(sourceItems));
  const visibleIds = computed(() => visibleItems.value.map((item) => normalizeId(item.id)));
  const selectedCount = computed(() => selectedIds.value.length);

  const isAllVisibleSelected = computed(() => {
    if (!visibleIds.value.length) {
      return false;
    }

    return visibleIds.value.every((id) => selectedIds.value.includes(id));
  });

  const isPartiallyVisibleSelected = computed(() => {
    if (!visibleIds.value.length) {
      return false;
    }

    const selectedVisibleCount = visibleIds.value.filter((id) => selectedIds.value.includes(id)).length;
    return selectedVisibleCount > 0 && selectedVisibleCount < visibleIds.value.length;
  });

  function isSelected(id) {
    return selectedIds.value.includes(normalizeId(id));
  }

  function setSelected(id, checked) {
    const normalizedId = normalizeId(id);
    const next = new Set(selectedIds.value);

    if (checked) {
      next.add(normalizedId);
    } else {
      next.delete(normalizedId);
    }

    selectedIds.value = Array.from(next);
  }

  function toggleSelected(id) {
    setSelected(id, !isSelected(id));
  }

  function setVisibleSelected(items, checked) {
    const ids = (items || []).map((item) => normalizeId(item.id));
    const next = new Set(selectedIds.value);

    ids.forEach((id) => {
      if (checked) {
        next.add(id);
      } else {
        next.delete(id);
      }
    });

    selectedIds.value = Array.from(next);
  }

  function clearSelection() {
    selectedIds.value = [];
  }

  function syncSelection(allowedIds) {
    const allowed = new Set((allowedIds || []).map((id) => normalizeId(id)));
    selectedIds.value = selectedIds.value.filter((id) => allowed.has(id));
  }

  watch(visibleIds, (ids) => {
    syncSelection(ids);
  });

  return {
    clearSelection,
    isAllVisibleSelected,
    isPartiallyVisibleSelected,
    isSelected,
    selectedCount,
    selectedIds,
    setSelected,
    setVisibleSelected,
    syncSelection,
    toggleSelected,
    visibleIds,
  };
}
