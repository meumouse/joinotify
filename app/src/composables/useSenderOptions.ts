import { computed, type ComputedRef } from 'vue';
import { useWorkflowBuilderStore } from '../stores/useWorkflowBuilderStore';
import { __, textDomain } from '../utils/i18n';

export interface SenderOption {
  label: string;
  value: string;
}

/**
 * Build the WhatsApp sender `<select>` options from the bootstrap phone
 * senders. The list is always prefixed with a placeholder option and includes
 * the currently selected value when it is not among the registered senders
 * (e.g. a sender saved before being removed).
 *
 * The current value is provided as a getter so callers can read it from
 * wherever it lives in their model (top-level `sender`, a nested `message`
 * object, etc.) while keeping the computed reactive.
 *
 * @param getCurrentSender Getter returning the currently selected sender value.
 */
export function useSenderOptions( getCurrentSender: () => unknown ): ComputedRef<SenderOption[]> {
  const store = useWorkflowBuilderStore();

  return computed( () => {
    const senders = Array.isArray( store.bootstrap?.phones?.senders ) ? store.bootstrap.phones.senders : [];
    const options = senders
      .map( ( item: unknown ) => String( ( item && typeof item === 'object' ? ( item as Record<string, unknown> ).phone : '' ) || '' ).trim() )
      .filter( Boolean )
      .map( ( phone: string ) => ( { label: phone, value: phone } ) );

    const current = String( getCurrentSender() || '' ).trim();

    if ( current && ! options.some( ( option ) => option.value === current ) ) {
      options.unshift( { label: current, value: current } );
    }

    return [ { label: __( '— Select a sender —', textDomain ), value: '' }, ...options ];
  } );
}
