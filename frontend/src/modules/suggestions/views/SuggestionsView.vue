<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/modules/auth/stores/auth';
import { useSuggestions } from '@/modules/suggestions/composables/useSuggestions';
import { useSuggestionsQuota } from '@/modules/suggestions/composables/useSuggestionsQuota';
import { useCreateSuggestion } from '@/modules/suggestions/composables/useCreateSuggestion';
import { useCastVote } from '@/modules/suggestions/composables/useCastVote';
import { useDeleteSuggestion } from '@/modules/suggestions/composables/useDeleteSuggestion';
import RankBadge from '@/modules/suggestions/components/RankBadge.vue';
import VoteButtons from '@/modules/suggestions/components/VoteButtons.vue';
import type { Suggestion } from '@/shared/api/schemas';

const { t, locale } = useI18n();
const auth = useAuthStore();

const page = ref(1);
const suggestionsQuery = useSuggestions(page);
const quotaQuery = useSuggestionsQuota();

const createMutation = useCreateSuggestion();
const voteMutation = useCastVote();
const deleteMutation = useDeleteSuggestion();

const suggestions = computed<Suggestion[]>(() => suggestionsQuery.data.value?.data ?? []);
const lastPage = computed<number>(() => suggestionsQuery.data.value?.last_page ?? 1);
const total = computed<number>(() => suggestionsQuery.data.value?.total ?? 0);

const quotaUsed = computed<number>(() => quotaQuery.data.value?.used ?? 0);
const quotaLimit = computed<number>(() => quotaQuery.data.value?.limit ?? 5);
const nextSlotAt = computed<string | null>(() => quotaQuery.data.value?.next_slot_at ?? null);
const quotaExhausted = computed(() => quotaUsed.value >= quotaLimit.value);

const dateFormatter = computed(
  () =>
    new Intl.DateTimeFormat(locale.value === 'pt-BR' ? 'pt-BR' : locale.value === 'es' ? 'es' : 'en', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
);

function formatDate(iso: string): string {
  try {
    return dateFormatter.value.format(new Date(iso));
  } catch {
    return iso;
  }
}

const nextSlotFormatted = computed(() => (nextSlotAt.value ? formatDate(nextSlotAt.value) : null));

// Modal state
const showModal = ref(false);
const form = ref({ title: '', body: '' });
const submitError = ref<string | null>(null);
const validationErrors = ref<Record<string, string[]>>({});

function openModal(): void {
  form.value = { title: '', body: '' };
  submitError.value = null;
  validationErrors.value = {};
  showModal.value = true;
}

function closeModal(): void {
  showModal.value = false;
}

async function submitNew(): Promise<void> {
  submitError.value = null;
  validationErrors.value = {};

  try {
    await createMutation.mutateAsync({ title: form.value.title.trim(), body: form.value.body.trim() });
    closeModal();
  } catch (e) {
    const err = e as { kind: string; message: string; errors?: Record<string, string[]> };
    if (err.kind === 'quota_exceeded') {
      submitError.value = t('suggestions.errors.quota_exceeded');
    } else if (err.kind === 'validation') {
      submitError.value = t('suggestions.errors.validation');
      validationErrors.value = err.errors ?? {};
    } else {
      submitError.value = t('suggestions.errors.unknown');
    }
  }
}

// Vote
const voteError = ref<string | null>(null);
let voteErrorTimeout: ReturnType<typeof setTimeout> | null = null;

async function onVote(suggestion: Suggestion, value: 'up' | 'down'): Promise<void> {
  voteError.value = null;
  try {
    await voteMutation.mutateAsync({ suggestionId: suggestion.id, value });
  } catch (e) {
    const err = e as { kind: string; message: string };
    voteError.value =
      err.kind === 'forbidden' ? t('suggestions.errors.cannot_vote_own') : t('suggestions.errors.unknown');
    if (voteErrorTimeout) clearTimeout(voteErrorTimeout);
    voteErrorTimeout = setTimeout(() => {
      voteError.value = null;
    }, 4000);
  }
}

// Delete
async function onDelete(suggestion: Suggestion): Promise<void> {
  if (!window.confirm(t('suggestions.confirm_delete'))) return;
  try {
    await deleteMutation.mutateAsync(suggestion.id);
  } catch {
    voteError.value = t('suggestions.errors.unknown');
  }
}

function isOwner(suggestion: Suggestion): boolean {
  return auth.user?.id === suggestion.user.id;
}

// Reset to page 1 if total drops (e.g., after deletes shrink list)
watch(total, (newTotal) => {
  if (page.value > 1 && newTotal <= (page.value - 1) * 20) {
    page.value = 1;
  }
});
</script>

<template>
  <section class="lg:flex lg:h-full lg:flex-col">
    <header class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-ink-900">
          {{ t('suggestions.title') }}
        </h1>
        <p class="mt-1 text-sm text-ink-500">
          {{ t('suggestions.subtitle') }}
        </p>
        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
          <span
            class="chip"
            :class="quotaExhausted ? 'bg-red-50 text-red-700' : 'bg-brand-50 text-brand-700'"
          >
            {{ t('suggestions.quota_used', { used: quotaUsed, limit: quotaLimit }) }}
          </span>
          <span
            v-if="nextSlotFormatted"
            class="text-ink-500"
          >
            {{ t('suggestions.quota_next_slot', { date: nextSlotFormatted }) }}
          </span>
        </div>
      </div>
      <button
        type="button"
        class="btn-primary self-start sm:self-auto"
        :disabled="quotaExhausted"
        :title="quotaExhausted ? t('suggestions.errors.quota_exceeded') : ''"
        @click="openModal"
      >
        <svg
          class="h-4 w-4"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M12 4v16m8-8H4"
          />
        </svg>
        {{ t('suggestions.new') }}
      </button>
    </header>

    <div
      v-if="voteError"
      class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-700"
      role="alert"
    >
      {{ voteError }}
    </div>

    <div
      v-if="suggestionsQuery.isLoading.value"
      class="text-sm text-ink-500"
    >
      {{ t('suggestions.loading') }}
    </div>

    <div
      v-else-if="suggestions.length === 0"
      class="card p-10 text-center"
    >
      <p class="text-sm text-ink-500">
        {{ t('suggestions.empty') }}
      </p>
    </div>

    <ul
      v-else
      class="space-y-3"
    >
      <li
        v-for="s in suggestions"
        :key="s.id"
        class="card p-4 sm:p-5"
      >
        <div class="flex items-start gap-3 sm:gap-5">
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs text-ink-500">
              <span class="font-medium text-ink-700">{{ s.user.name }}</span>
              <RankBadge
                v-if="s.rank !== null"
                :rank="s.rank"
                placement="inline"
              />
              <span aria-hidden="true">·</span>
              <span>{{ formatDate(s.created_at) }}</span>
            </div>

            <h3 class="mt-2 text-base font-semibold text-ink-900 break-words">
              {{ s.title }}
            </h3>
            <p class="mt-1 whitespace-pre-wrap text-sm text-ink-600 break-words">
              {{ s.body }}
            </p>

            <div
              v-if="isOwner(s)"
              class="mt-3 flex justify-end"
            >
              <button
                type="button"
                class="text-xs font-medium text-red-600 hover:text-red-700"
                :disabled="deleteMutation.isPending.value"
                @click="onDelete(s)"
              >
                {{ t('suggestions.delete') }}
              </button>
            </div>
          </div>

          <div class="flex w-12 shrink-0 justify-center sm:w-16">
            <VoteButtons
              :my-vote="s.my_vote"
              :score="s.score"
              :disabled="isOwner(s)"
              :disabled-reason="isOwner(s) ? t('suggestions.errors.cannot_vote_own') : undefined"
              @vote="(v) => onVote(s, v)"
            />
          </div>
        </div>
      </li>
    </ul>

    <nav
      v-if="lastPage > 1"
      class="mt-6 flex items-center justify-center gap-2"
      aria-label="Pagination"
    >
      <button
        type="button"
        class="btn-secondary"
        :disabled="page <= 1"
        @click="page = Math.max(1, page - 1)"
      >
        {{ t('suggestions.prev') }}
      </button>
      <span class="text-sm text-ink-500">
        {{ t('suggestions.page_of', { current: page, total: lastPage }) }}
      </span>
      <button
        type="button"
        class="btn-secondary"
        :disabled="page >= lastPage"
        @click="page = Math.min(lastPage, page + 1)"
      >
        {{ t('suggestions.next') }}
      </button>
    </nav>

    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
        @click.self="closeModal"
      >
        <div class="w-full max-w-lg rounded-2xl border border-ink-200 bg-white shadow-card mx-4">
          <div class="border-b border-ink-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-ink-900">
              {{ t('suggestions.modal_title') }}
            </h2>
          </div>

          <div class="space-y-4 px-6 py-5">
            <div>
              <label class="label">{{ t('suggestions.title_label') }} *</label>
              <input
                v-model="form.title"
                class="input"
                :placeholder="t('suggestions.title_placeholder')"
                maxlength="120"
              >
              <p
                v-if="validationErrors.title?.length"
                class="mt-1 text-xs text-red-600"
              >
                {{ validationErrors.title[0] }}
              </p>
            </div>

            <div>
              <label class="label">{{ t('suggestions.body_label') }} *</label>
              <textarea
                v-model="form.body"
                class="input"
                rows="6"
                maxlength="2000"
                :placeholder="t('suggestions.body_placeholder')"
              />
              <p
                v-if="validationErrors.body?.length"
                class="mt-1 text-xs text-red-600"
              >
                {{ validationErrors.body[0] }}
              </p>
            </div>

            <p
              v-if="submitError && !validationErrors.title?.length && !validationErrors.body?.length"
              class="text-sm text-red-600"
            >
              {{ submitError }}
            </p>
          </div>

          <div class="flex justify-end gap-2 border-t border-ink-100 px-6 py-4">
            <button
              type="button"
              class="btn-secondary"
              :disabled="createMutation.isPending.value"
              @click="closeModal"
            >
              {{ t('suggestions.cancel') }}
            </button>
            <button
              type="button"
              class="btn-primary"
              :disabled="createMutation.isPending.value || !form.title.trim() || !form.body.trim()"
              @click="submitNew"
            >
              {{ createMutation.isPending.value ? '...' : t('suggestions.save') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </section>
</template>
