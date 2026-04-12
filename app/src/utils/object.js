export function cloneValue(value) {
  return JSON.parse(JSON.stringify(value || {}));
}

export function deepEqual(a, b) {
  if (a === b) {
    return true;
  }

  if (!a || !b || typeof a !== 'object' || typeof b !== 'object') {
    return false;
  }

  const aKeys = Object.keys(a).sort();
  const bKeys = Object.keys(b).sort();

  if (aKeys.length !== bKeys.length) {
    return false;
  }

  for (let index = 0; index < aKeys.length; index += 1) {
    if (aKeys[index] !== bKeys[index]) {
      return false;
    }
  }

  for (const key of aKeys) {
    const aValue = a[key];
    const bValue = b[key];

    if (Array.isArray(aValue) || Array.isArray(bValue)) {
      if (JSON.stringify(aValue) !== JSON.stringify(bValue)) {
        return false;
      }
      continue;
    }

    if (aValue && bValue && typeof aValue === 'object' && typeof bValue === 'object') {
      if (!deepEqual(aValue, bValue)) {
        return false;
      }
      continue;
    }

    if (aValue !== bValue) {
      return false;
    }
  }

  return true;
}
