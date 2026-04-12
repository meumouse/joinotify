<script setup>
import { computed } from 'vue';
import SettingsPage from './pages/settings/SettingsPage.vue';
import LicensePage from './pages/license/LicensePage.vue';
import BuilderPage from './pages/builder/BuilderPage.vue';
import WorkflowsPage from './pages/workflows/WorkflowsPage.vue';

const props = defineProps({
  bootstrap: { type: Object, default: () => ({}) },
});

const bootstrap = computed(() => props.bootstrap || {});
const pages = {
  settings: SettingsPage,
  license: LicensePage,
  builder: BuilderPage,
  workflows: WorkflowsPage,
};

const currentPage = computed(() => pages[bootstrap.value.page] || SettingsPage);
</script>

<template>
  <component :is="currentPage" :bootstrap="bootstrap" />
</template>
