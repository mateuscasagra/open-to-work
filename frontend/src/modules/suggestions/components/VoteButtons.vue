<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
  myVote: 1 | -1 | null;
  score: number;
  disabled: boolean;
  disabledReason?: string;
}>();

const emit = defineEmits<{
  vote: [value: 'up' | 'down'];
}>();

const upActiveClass = computed(() =>
  props.myVote === 1
    ? 'text-brand-600 bg-brand-50'
    : 'text-ink-400 hover:text-brand-600 hover:bg-brand-50/60'
);
const downActiveClass = computed(() =>
  props.myVote === -1
    ? 'text-red-600 bg-red-50'
    : 'text-ink-400 hover:text-red-600 hover:bg-red-50/60'
);

const scoreColor = computed(() => {
  if (props.score > 0) return 'text-brand-700';
  if (props.score < 0) return 'text-red-700';
  return 'text-ink-700';
});

function onClick(value: 'up' | 'down'): void {
  if (props.disabled) return;
  emit('vote', value);
}
</script>

<template>
  <div class="flex flex-col items-center gap-1.5 select-none">
    <button
      type="button"
      class="grid h-9 w-9 place-items-center rounded-lg transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
      :class="[
        upActiveClass,
        disabled ? 'opacity-30 cursor-not-allowed hover:bg-transparent' : 'cursor-pointer',
      ]"
      :disabled="disabled"
      :title="disabled ? (disabledReason ?? '') : 'Upvote'"
      :aria-label="disabled ? (disabledReason ?? 'Upvote disabled') : 'Upvote'"
      @click="onClick('up')"
    >
      <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
      </svg>
    </button>
    <span
      class="text-sm font-bold tabular-nums"
      :class="scoreColor"
    >{{ score }}</span>
    <button
      type="button"
      class="grid h-9 w-9 place-items-center rounded-lg transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
      :class="[
        downActiveClass,
        disabled ? 'opacity-30 cursor-not-allowed hover:bg-transparent' : 'cursor-pointer',
      ]"
      :disabled="disabled"
      :title="disabled ? (disabledReason ?? '') : 'Downvote'"
      :aria-label="disabled ? (disabledReason ?? 'Downvote disabled') : 'Downvote'"
      @click="onClick('down')"
    >
      <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
      </svg>
    </button>
  </div>
</template>
