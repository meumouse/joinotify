/**
 * settingsSections.js frontend source file.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
export const SETTINGS_SECTION_ICONS = {
  general: 'settings-general',
  phones: 'settings-phones',
  integrations: 'settings-integrations',
  about: 'settings-about',
};

export function getSettingsSectionIcon(sectionId) {
  return SETTINGS_SECTION_ICONS[sectionId] || SETTINGS_SECTION_ICONS.general;
}
