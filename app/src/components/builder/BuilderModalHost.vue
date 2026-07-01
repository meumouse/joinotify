<!--
  BuilderModalHost.vue

  Passthrough host that renders whatever modal component is passed into its
  default slot. Centralizes the mount point so the builder shell only needs
  one place in the template to render "the currently open modal, if any".

  @since 2.0.0
-->
<template>
  <slot />
</template>
