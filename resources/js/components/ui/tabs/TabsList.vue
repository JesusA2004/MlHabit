<script setup lang="ts">
import type { TabsListProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { cn } from "@/lib/utils"
import { TabsIndicator, TabsList } from "reka-ui"

const props = defineProps<TabsListProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")
</script>

<template>
  <TabsList
    data-slot="tabs-list"
    v-bind="delegatedProps"
    :class="cn(
      'bg-muted text-muted-foreground relative inline-flex h-9 w-fit items-center justify-center rounded-lg p-0.75',
      props.class,
    )"
  >
    <!--
      Pastilla que se desliza a la pestaña activa. Mientras no se mide (primer
      pintado, sin JS) la pestaña activa conserva su propio fondo; ver
      `tabs-indicator` en app.css.
    -->
    <TabsIndicator
      data-slot="tabs-indicator"
      class="pointer-events-none absolute top-0.75 bottom-0.75 left-0 w-(--reka-tabs-indicator-size) translate-x-(--reka-tabs-indicator-position) rounded-md bg-background shadow-sm transition-[width,translate] duration-300 ease-out"
      aria-hidden="true"
    />
    <slot />
  </TabsList>
</template>
