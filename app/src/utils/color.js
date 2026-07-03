/**
 * Color utilities shared by field components.
 *
 * @since 1.4.7
 * @version 1.4.7
 */

export const SHADE_STEPS = ['0', '50', '100', '200', '300', '400', '500', '600', '700', '800', '900', '950'];

/**
 * Normalize a hex color string.
 *
 * Accepts 3-digit and 6-digit hex values and returns a lowercase 6-digit hex.
 *
 * @param {string} value Raw color value.
 * @return {string} Normalized color or empty string.
 */
export function normalizeHex(value) {
  const raw = String(value || '').trim().replace(/^#/, '');

  if (/^[0-9a-fA-F]{3}$/.test(raw)) {
    return `#${raw
      .split('')
      .map((channel) => `${channel}${channel}`)
      .join('')
      .toLowerCase()}`;
  }

  if (!/^[0-9a-fA-F]{6}$/.test(raw)) {
    return '';
  }

  return `#${raw.toLowerCase()}`;
}

/**
 * Convert a hex color to RGB channels.
 *
 * @param {string} hex Hex color.
 * @return {number[]} RGB channels.
 */
export function hexToRgb(hex) {
  const value = normalizeHex(hex).replace('#', '');

  if (value.length !== 6) {
    return [79, 70, 229];
  }

  return [
    Number.parseInt(value.slice(0, 2), 16),
    Number.parseInt(value.slice(2, 4), 16),
    Number.parseInt(value.slice(4, 6), 16),
  ];
}

/**
 * Convert RGB channels to a hex color.
 *
 * @param {number[]} color RGB channels.
 * @return {string} Hex color.
 */
export function rgbToHex(color) {
  const channels = color.map((value) => Math.max(0, Math.min(255, Number.parseInt(value, 10) || 0)));

  return `#${channels.map((value) => value.toString(16).padStart(2, '0')).join('')}`;
}

/**
 * Calculate relative luminance.
 *
 * @param {number} r Red channel.
 * @param {number} g Green channel.
 * @param {number} b Blue channel.
 * @return {number} Relative luminance.
 */
export function luminance(r, g, b) {
  const channels = [r, g, b].map((channel) => {
    const value = channel / 255;
    return value <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
  });

  return (channels[0] * 0.2126) + (channels[1] * 0.7152) + (channels[2] * 0.0722);
}

/**
 * Blend two colors using a ratio.
 *
 * @param {number[]} color1 First color.
 * @param {number[]} color2 Second color.
 * @param {number} factor Blend factor between 0 and 1.
 * @return {number[]} Interpolated color.
 */
export function interpolateColor(color1, color2, factor) {
  const safeFactor = Math.max(0, Math.min(1, Number.parseFloat(factor) || 0));
  const result = [];

  for (let index = 0; index < color1.length; index += 1) {
    result.push(Math.round(color1[index] + ((color2[index] - color1[index]) * safeFactor)));
  }

  return result;
}

/**
 * Generate a readable color palette from a base color.
 *
 * @param {string} hex Base color.
 * @return {Record<string, string>} Generated palette.
 */
export function generatePalette(hex) {
  const inputColor = hexToRgb(hex);
  const inputLuminance = luminance(inputColor[0], inputColor[1], inputColor[2]);
  const lightestColor = [245, 245, 245];
  const darkestColor = [8, 8, 8];
  const lightestLuminance = luminance(245, 245, 245);
  const darkestLuminance = luminance(8, 8, 8);
  const luminanceRange = lightestLuminance - darkestLuminance;
  const rows = { 0: '#ffffff' };

  SHADE_STEPS.slice(1).forEach((step) => {
    const targetLuminance = lightestLuminance - ((Number(step) / 1000) * luminanceRange);
    let resultColor;

    if (targetLuminance > inputLuminance) {
      const factor = (targetLuminance - inputLuminance) / Math.max(lightestLuminance - inputLuminance, 0.0001);
      resultColor = interpolateColor(inputColor, lightestColor, factor);
    } else {
      const darkFactor = (inputLuminance - targetLuminance) / Math.max(inputLuminance - darkestLuminance, 0.0001);
      resultColor = interpolateColor(inputColor, darkestColor, darkFactor);
    }

    rows[String(step)] = rgbToHex(resultColor);
  });

  return rows;
}
