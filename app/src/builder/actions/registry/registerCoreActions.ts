import { conditionDefinition } from '../definitions/condition';
import { snippetPhpDefinition } from '../definitions/snippetPhp';
import { stopFunnelDefinition } from '../definitions/stopFunnel';
import { timeDelayDefinition } from '../definitions/timeDelay';
import { registerBuilderAction } from './actionRegistry';

export function registerCoreActions(): void {
  registerBuilderAction(timeDelayDefinition);
  registerBuilderAction(conditionDefinition);
  registerBuilderAction(stopFunnelDefinition);
  registerBuilderAction(snippetPhpDefinition);
}
