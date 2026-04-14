<template>
  <section class="canvas-dot relative h-full w-full overflow-hidden">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_1px_1px,rgba(148,163,184,0.2)_1px,transparent_0)] [background-size:22px_22px] opacity-90" />
    <div class="relative z-10">
      <slot />
    </div>
  </section>
</template>
