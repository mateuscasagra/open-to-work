<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
  rank: 1 | 2 | 3 | null;
  placement: 'above' | 'inline';
}>();

const CROWN_PATH = 'M2.25 18L3 9l4.5 4.5L12 6l4.5 7.5L21 9l.75 9H2.25z M2.25 21h19.5';

const TROPHY_PATH = 'M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 002.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 012.916.52 6.003 6.003 0 01-5.395 4.972m0 0a6.726 6.726 0 01-2.749 1.35m0 0a6.772 6.772 0 01-3.044 0';

const colorClass = computed(() => {
  if (props.rank === 1) return 'text-amber-500';
  if (props.rank === 2) return 'text-slate-400';
  if (props.rank === 3) return 'text-amber-700';
  return '';
});

const iconPath = computed(() => (props.rank === 1 ? CROWN_PATH : TROPHY_PATH));

const sizeClass = computed(() => (props.placement === 'above' ? 'h-6 w-6' : 'h-5 w-5'));

const wrapperClass = computed(() =>
  props.placement === 'above'
    ? 'flex justify-center mb-1'
    : 'inline-flex items-center mr-1.5 align-middle'
);

const titleByRank = computed(() => {
  if (props.rank === 1) return 'Top suggestion';
  if (props.rank === 2) return 'Second place';
  if (props.rank === 3) return 'Third place';
  return '';
});
</script>

<template>
  <span
    v-if="rank !== null"
    :class="[wrapperClass, colorClass]"
    :title="titleByRank"
  >
    <svg
      :class="sizeClass"
      fill="none"
      stroke="currentColor"
      viewBox="0 0 24 24"
      :aria-label="titleByRank"
    >
      <path
        stroke-linecap="round"
        stroke-linejoin="round"
        stroke-width="1.75"
        :d="iconPath"
      />
    </svg>
  </span>
</template>
