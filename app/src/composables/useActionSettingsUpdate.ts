/**
 * Shared `update(key, value)` helper for action settings components that bind
 * to a plain-object `modelValue` and emit a shallow-merged copy on change.
 *
 * Usage:
 *   const { update } = useActionSettingsUpdate( props, emit );
 *   update( 'sender', value );
 *
 * @param props An object exposing the reactive `modelValue` prop.
 * @param emit  The component's emit function.
 */
export function useActionSettingsUpdate(
  props: { modelValue: object },
  emit: ( event: 'update:modelValue', value: Record<string, unknown> ) => void,
) {
  function update( key: string, value: unknown ) {
    emit( 'update:modelValue', {
      ...( props.modelValue as Record<string, unknown> ),
      [ key ]: value,
    } );
  }

  return { update };
}
