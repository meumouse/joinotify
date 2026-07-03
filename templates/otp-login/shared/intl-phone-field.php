<?php
/**
 * Reusable international phone field.
 *
 * @var string $field_id
 * @var string $field_name
 * @var string $label
 * @var string $context
 * @var string $helper
 *
 * @since 2.0.0
 */

defined('ABSPATH') || exit;

$field_id = $field_id ?? 'joinotify-phone';
$field_name = $field_name ?? 'phone';
$label = $label ?? __( 'Phone number', 'joinotify' );
$context = $context ?? 'default';
$helper = $helper ?? '';
?>

<p class="space-y-2">
	<label for="<?php echo esc_attr( $field_id ); ?>" class="block text-sm font-semibold text-slate-700">
		<?php echo esc_html( $label ); ?>
		<span class="required" aria-hidden="true">*</span>
	</label>
	<input
		type="tel"
		class="joinotify-otp-login__input w-full border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400"
		id="<?php echo esc_attr( $field_id ); ?>"
		data-phone-visible
		autocomplete="tel"
		inputmode="tel"
	/>
	<input type="hidden" name="<?php echo esc_attr( $field_name ); ?>" data-phone-hidden />

	<?php if ( ! empty( $helper ) ) : ?>
		<small class="block text-xs leading-5 text-slate-500"><?php echo esc_html( $helper ); ?></small>
	<?php endif; ?>
</p>
