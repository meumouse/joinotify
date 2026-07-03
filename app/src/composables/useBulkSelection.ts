/**
 * useBulkSelection.ts
 *
 * Composable that tracks a set of selected item IDs across a (possibly reactive)
 * list of visible items. Provides helpers for toggling, bulk-selecting the
 * visible page, and automatically pruning selections that are no longer visible.
 *
 * @since 2.0.0
 */
import { computed, ref, unref, watch } from 'vue';

/**
 * Resolves the source items, supporting either a getter function or a ref/value.
 *
 * @since 2.0.0
 * @param {Function|Ref<Array>|Array} source Items source (function, ref, or array).
 * @returns {Array} The resolved array of items.
 */
function resolveItems(source) {
  if (typeof source === 'function') {
    return source() || [];
  }

  return unref(source) || [];
}

/**
 * Normalizes an ID to a string so comparisons are type-consistent.
 *
 * @since 2.0.0
 * @param {string|number} id The raw ID.
 * @returns {string} The stringified ID.
 */
function normalizeId(id) {
  return String(id);
}

/**
 * Manages bulk selection state over a list of items.
 *
 * @since 2.0.0
 * @param {Function|Ref<Array>|Array} sourceItems The visible items source.
 * @returns {Object} Selection state and mutators.
 */
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

  /**
   * Checks whether an item ID is currently selected.
   *
   * @since 2.0.0
   * @param {string|number} id The item ID.
   * @returns {boolean} True when the ID is selected.
   */
  function isSelected(id) {
    return selectedIds.value.includes(normalizeId(id));
  }

  /**
   * Adds or removes a single item ID from the selection.
   *
   * @since 2.0.0
   * @param {string|number} id The item ID.
   * @param {boolean} checked Whether the item should be selected.
   */
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

  /**
   * Toggles the selection state of a single item ID.
   *
   * @since 2.0.0
   * @param {string|number} id The item ID.
   */
  function toggleSelected(id) {
    setSelected(id, !isSelected(id));
  }

  /**
   * Selects or deselects every item in the provided list.
   *
   * @since 2.0.0
   * @param {Array} items Items whose IDs should be updated.
   * @param {boolean} checked Whether the items should be selected.
   */
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

  /**
   * Clears the entire selection.
   *
   * @since 2.0.0
   */
  function clearSelection() {
    selectedIds.value = [];
  }

  /**
   * Removes any selected IDs that are not part of the allowed set.
   *
   * @since 2.0.0
   * @param {Array} allowedIds IDs that are permitted to stay selected.
   */
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
