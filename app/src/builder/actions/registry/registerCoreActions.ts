/**
 * registerCoreActions.ts
 *
 * Registers the built-in builder action definitions (delay, condition, stop,
 * PHP snippet, WhatsApp text/media/AI messages, Telegram message, Resend
 * e-mail, smart variable, and coupon) into the action registry during bootstrap.
 *
 * @since 2.0.0
 */
import { conditionDefinition } from '../definitions/condition';
import { createCouponDefinition } from '../definitions/createCoupon';
import { dynamicPlaceholderDefinition } from '../definitions/dynamicPlaceholder';
import { snippetPhpDefinition } from '../definitions/snippetPhp';
import { stopFunnelDefinition } from '../definitions/stopFunnel';
import { timeDelayDefinition } from '../definitions/timeDelay';
import { whatsappAiMessageDefinition } from '../definitions/whatsappAiMessage';
import { whatsappMediaDefinition } from '../definitions/whatsappMedia';
import { whatsappTextDefinition } from '../definitions/whatsappText';
import { telegramTextDefinition } from '../definitions/telegramText';
import { resendEmailDefinition } from '../definitions/resendEmail';
import { registerBuilderAction } from './actionRegistry';

/**
 * Registers all core action definitions into the builder action registry.
 *
 * @since 2.0.0
 * @returns {void}
 */
export function registerCoreActions(): void {
  registerBuilderAction(timeDelayDefinition);
  registerBuilderAction(conditionDefinition);
  registerBuilderAction(stopFunnelDefinition);
  registerBuilderAction(snippetPhpDefinition);
  registerBuilderAction(whatsappTextDefinition);
  registerBuilderAction(whatsappMediaDefinition);
  registerBuilderAction(whatsappAiMessageDefinition);
  registerBuilderAction(telegramTextDefinition);
  registerBuilderAction(resendEmailDefinition);
  registerBuilderAction(dynamicPlaceholderDefinition);
  registerBuilderAction(createCouponDefinition);
}
