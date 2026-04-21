<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useResumes } from '@/modules/resumes/composables/useResumes';
import { useApplyToJob } from '@/modules/applications/composables/useApplyToJob';
import type { Job, Profile } from '@/shared/api/schemas';

const props = defineProps<{
  modelValue: boolean;
  job: Job;
  profile: Profile;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: boolean];
  applied: [];
}>();

const { t } = useI18n();
const resumes = useResumes();
const applyMutation = useApplyToJob();

const message = ref('');
const selectedResumeId = ref<number | null>(null);
const sending = ref(false);

const showMessageField = computed(() => props.profile.email_apply_message_mode === 'variable');
const showResumeField = computed(() => props.profile.email_apply_resume_mode === 'variable');

watch(
  () => props.modelValue,
  (open) => {
    if (open) {
      // Pre-fill message with template, replacing placeholders
      const template = props.profile.email_apply_message_template ?? '';
      message.value = template
        .replace(/\{empresa\}/g, props.job.company?.name ?? '')
        .replace(/\{cargo\}/g, props.job.title ?? '');
      selectedResumeId.value = props.profile.email_apply_resume_id ?? null;
    }
  },
);

function close(): void {
  emit('update:modelValue', false);
}

async function onSubmit(): Promise<void> {
  sending.value = true;
  try {
    await applyMutation.mutateAsync({
      jobId: props.job.id,
      source: 'email',
      email_message_override: showMessageField.value ? message.value : undefined,
      email_resume_id_override: showResumeField.value && selectedResumeId.value
        ? selectedResumeId.value
        : undefined,
    });
    emit('applied');
    close();
  } catch {
    // error handled by mutation
  } finally {
    sending.value = false;
  }
}
</script>

<template>
  <teleport to="body">
    <div
      v-if="modelValue"
      class="fixed inset-0 z-[100] flex items-center justify-center p-4"
    >
      <!-- Backdrop -->
      <div
        class="absolute inset-0 bg-ink-900/50 backdrop-blur-sm"
        @click="close"
      />

      <!-- Modal -->
      <div class="relative w-full max-w-lg rounded-xl border border-ink-200 bg-white p-6 shadow-card">
        <!-- Header -->
        <div class="mb-5 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-ink-900">
            {{ t('jobs.apply_modal_title') }}
          </h2>
          <button
            type="button"
            class="rounded-lg p-1 text-ink-400 hover:bg-ink-100 hover:text-ink-700"
            @click="close"
          >
            <svg
              class="h-5 w-5"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M6 18L18 6M6 6l12 12"
              />
            </svg>
          </button>
        </div>

        <!-- Job info -->
        <div class="mb-5 rounded-lg bg-ink-50 p-3">
          <p class="font-medium text-ink-900">
            {{ job.title }}
          </p>
          <p class="text-sm text-ink-500">
            {{ job.company?.name ?? '—' }}
          </p>
        </div>

        <form
          class="space-y-4"
          @submit.prevent="onSubmit"
        >
          <!-- Message field (variable mode) -->
          <div v-if="showMessageField">
            <label class="label">{{ t('jobs.apply_modal_message') }}</label>
            <textarea
              v-model="message"
              rows="5"
              class="input mt-1.5"
              :placeholder="t('profile.email_apply_template_hint')"
            />
          </div>

          <!-- Resume field (variable mode) -->
          <div v-if="showResumeField">
            <label class="label">{{ t('jobs.apply_modal_resume') }}</label>
            <select
              v-model="selectedResumeId"
              class="input mt-1.5"
            >
              <option :value="null">
                {{ t('profile.email_apply_resume_select') }}
              </option>
              <option
                v-for="resume in resumes.data.value?.data ?? []"
                :key="resume.id"
                :value="resume.id"
              >
                {{ resume.title }}{{ resume.is_pdf_upload ? ' (PDF)' : '' }}
              </option>
            </select>
          </div>

          <!-- Actions -->
          <div class="flex items-center justify-end gap-3 border-t border-ink-200 pt-4">
            <button
              type="button"
              class="btn-secondary"
              @click="close"
            >
              {{ t('account.delete.cancel') }}
            </button>
            <button
              type="submit"
              class="btn-primary"
              :disabled="sending"
            >
              {{ sending ? t('jobs.apply_modal_sending') : t('jobs.apply_modal_send') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </teleport>
</template>
