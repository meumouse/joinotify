import { conditionDefinition } from '../definitions/condition';
import { createCouponDefinition } from '../definitions/createCoupon';
import { snippetPhpDefinition } from '../definitions/snippetPhp';
import { stopFunnelDefinition } from '../definitions/stopFunnel';
import { timeDelayDefinition } from '../definitions/timeDelay';
import { whatsappAiMessageDefinition } from '../definitions/whatsappAiMessage';
import { whatsappMediaDefinition } from '../definitions/whatsappMedia';
import { whatsappTextDefinition } from '../definitions/whatsappText';
import { registerBuilderAction } from './actionRegistry';

export function registerCoreActions(): void {
  registerBuilderAction(timeDelayDefinition);
  registerBuilderAction(conditionDefinition);
  registerBuilderAction(stopFunnelDefinition);
  registerBuilderAction(snippetPhpDefinition);
  registerBuilderAction(whatsappTextDefinition);
  registerBuilderAction(whatsappMediaDefinition);
  registerBuilderAction(whatsappAiMessageDefinition);
  registerBuilderAction(createCouponDefinition);
}
