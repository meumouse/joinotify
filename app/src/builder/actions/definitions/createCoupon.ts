import CreateCouponSettings from '../settings/CreateCouponSettings.vue';
import { truncateDescription } from '../utils/actionDescription';
import type { ActionDefinition } from '../registry/types';

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}

function normalizeCreateCouponData(data: Record<string, unknown>): Record<string, unknown> {
  const settings = asRecord(data.settings);
  const expiry = asRecord(settings.expiry_data);
  const message = asRecord(settings.message);
  const couponMessage = String(message.message || '');

  return {
    title: String(data.title || 'Discount coupon'),
    description: String(data.description || ''),
    action: 'create_coupon',
    settings: {
      generate_coupon: String(settings.generate_coupon || 'yes'),
      coupon_code: String(settings.coupon_code || ''),
      coupon_description: String(settings.coupon_description || ''),
      discount_type: String(settings.discount_type || 'fixed_cart'),
      coupon_amount: settings.coupon_amount ?? '',
      free_shipping: String(settings.free_shipping || 'no'),
      coupon_expiry: String(settings.coupon_expiry || 'no'),
      expiry_data: {
        type: String(expiry.type || 'period'),
        delay_value: expiry.delay_value ?? 1,
        delay_period: String(expiry.delay_period || 'day'),
        date_value: String(expiry.date_value || ''),
        time_value: String(expiry.time_value || ''),
      },
      message: {
        sender: String(message.sender || ''),
        receiver: String(message.receiver || '{{ wc_billing_phone }}'),
        message: couponMessage,
      },
    },
  };
}

export const createCouponDefinition: ActionDefinition = {
  action: 'create_coupon',
  title: 'Discount coupon',
  description: 'Generate a WooCommerce coupon and notify the customer.',
  icon: 'purchase-tag',
  context: ['woocommerce'],
  hasSettings: true,
  priority: 60,
  isExpansible: false,
  defaultData: normalizeCreateCouponData({}),
  settingsComponent: CreateCouponSettings,
  normalizeData: normalizeCreateCouponData,
  serializeData: normalizeCreateCouponData,
  buildDescription: (data) => {
    const settings = asRecord(data.settings);
    const message = asRecord(settings.message);
    const summary = String(message.message || '') || `Coupon: ${String(settings.discount_type || 'fixed_cart')}`;
    return truncateDescription(summary);
  },
};
