import { computed, type ComputedRef } from 'vue';
import { useWorkflowBuilderStore } from '../stores/useWorkflowBuilderStore';
import { __, textDomain } from '../utils/i18n';

export interface AiSelectOption {
  label: string;
  value: string;
}

interface AiProviderConfig {
  value: string;
  label: string;
  models: AiSelectOption[];
}

/**
 * Read the AI provider routing config exposed on the builder bootstrap payload
 * and derive the select options used by the AI action settings.
 *
 * Only providers with configured credentials are present, so the provider
 * selector is meant to be shown only when `hasMultipleProviders` is true — with
 * a single (or no) engine there is nothing to route.
 *
 * @since 2.1.0
 */
export function useAiProviders() {
  const store = useWorkflowBuilderStore();

  const providers: ComputedRef<AiProviderConfig[]> = computed( () => {
    const raw = ( store.bootstrap as Record<string, unknown> | undefined )?.ai as
      | Record<string, unknown>
      | undefined;
    const list = raw?.providers;

    if ( ! Array.isArray( list ) ) {
      return [];
    }

    return list
      .map( ( item ): AiProviderConfig | null => {
        if ( ! item || typeof item !== 'object' ) {
          return null;
        }

        const entry = item as Record<string, unknown>;
        const value = String( entry.value || '' ).trim();

        if ( ! value ) {
          return null;
        }

        const models = Array.isArray( entry.models )
          ? entry.models
              .map( ( model ): AiSelectOption | null => {
                if ( ! model || typeof model !== 'object' ) {
                  return null;
                }

                const modelEntry = model as Record<string, unknown>;
                const modelValue = String( modelEntry.value || '' ).trim();

                if ( ! modelValue ) {
                  return null;
                }

                return { value: modelValue, label: String( modelEntry.label || modelValue ) };
              } )
              .filter( ( model ): model is AiSelectOption => model !== null )
          : [];

        return { value, label: String( entry.label || value ), models };
      } )
      .filter( ( entry ): entry is AiProviderConfig => entry !== null );
  } );

  const defaultProvider: ComputedRef<string> = computed( () => {
    const raw = ( store.bootstrap as Record<string, unknown> | undefined )?.ai as
      | Record<string, unknown>
      | undefined;
    const configured = String( raw?.default_provider || '' ).trim();

    if ( configured && providers.value.some( ( provider ) => provider.value === configured ) ) {
      return configured;
    }

    return providers.value[0]?.value ?? '';
  } );

  const hasMultipleProviders: ComputedRef<boolean> = computed( () => providers.value.length > 1 );

  // Leading empty option keeps "use the provider configured in settings".
  const providerSelectOptions: ComputedRef<AiSelectOption[]> = computed( () => [
    { label: __( 'Use default provider', textDomain ), value: '' },
    ...providers.value.map( ( provider ) => ( { label: provider.label, value: provider.value } ) ),
  ] );

  /**
   * Resolve which provider a node effectively targets. An empty or unknown
   * override falls back to the default provider.
   */
  function resolveProviderId( current?: unknown ): string {
    const value = String( current || '' ).trim();

    if ( value && providers.value.some( ( provider ) => provider.value === value ) ) {
      return value;
    }

    return defaultProvider.value;
  }

  /**
   * Model `<select>` options for a provider, prefixed with the "use default"
   * entry. An empty `ai_model` means "use the provider default from settings".
   */
  function modelOptions( providerId: string ): AiSelectOption[] {
    const provider = providers.value.find( ( entry ) => entry.value === providerId );
    const models = provider?.models ?? [];

    return [ { label: __( 'Use default model', textDomain ), value: '' }, ...models ];
  }

  return {
    providers,
    providerSelectOptions,
    hasMultipleProviders,
    defaultProvider,
    resolveProviderId,
    modelOptions,
  };
}
