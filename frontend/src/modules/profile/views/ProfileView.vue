<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useProfile, searchSkills } from '../composables/useProfile';
import { useSupportedCountries } from '../composables/useLocationLookup';
import LocationFields from '../components/LocationFields.vue';
import { useResumes } from '@/modules/resumes/composables/useResumes';
import type { Skill, Seniority, Modality, Locale } from '@/shared/api/schemas';

const MAX_SKILLS = 8;
const HIGHLIGHT_INDEX_NONE = -1;

const { t } = useI18n();
const { profile, loading, fetch, save } = useProfile();
const resumes = useResumes();
const countriesQuery = useSupportedCountries();

const form = ref({
  desired_role: '',
  seniority: null as Seniority | null,
  modality: null as Modality | null,
  salary_min: null as number | null,
  salary_max: null as number | null,
  salary_currency: 'BRL',
  country_code: null as string | null,
  postal_code: null as string | null,
  state_code: null as string | null,
  state_name: '',
  city: '',
  languages: [] as Locale[],
  bio: '',
  email_apply_enabled: false,
  email_apply_message_mode: null as 'fixed' | 'variable' | null,
  email_apply_message_template: '',
  email_apply_resume_mode: null as 'fixed' | 'variable' | null,
  email_apply_resume_id: null as number | null,
});

const locationModel = computed({
  get: () => ({
    country_code: form.value.country_code,
    postal_code: form.value.postal_code,
    state_code: form.value.state_code,
    state_name: form.value.state_name,
    city: form.value.city,
  }),
  set: (value) => {
    form.value.country_code = value.country_code;
    form.value.postal_code = value.postal_code;
    form.value.state_code = value.state_code;
    form.value.state_name = value.state_name;
    form.value.city = value.city;
  },
});
const selectedSkills = ref<Skill[]>([]);
const skillQuery = ref('');
const skillSuggestions = ref<Skill[]>([]);
const highlightIdx = ref(HIGHLIGHT_INDEX_NONE);
const skillDropdownOpen = ref(false);
const fieldErrors = ref<Record<string, string[]>>({});
const savedFlash = ref(false);
const saveError = ref<string | null>(null);

const seniorities: Seniority[] = ['intern', 'junior', 'mid', 'senior', 'staff', 'principal'];
const modalities: Modality[] = ['remote', 'hybrid', 'onsite'];
const locales: Locale[] = ['pt_BR', 'en', 'es'];

onMounted(async () => {
  await fetch();
  if (profile.value) {
    form.value = {
      desired_role: profile.value.desired_role ?? '',
      seniority: profile.value.seniority,
      modality: profile.value.modality,
      salary_min: profile.value.salary_min,
      salary_max: profile.value.salary_max,
      salary_currency: profile.value.salary_currency ?? 'BRL',
      country_code: profile.value.country_code ?? null,
      postal_code: profile.value.postal_code ?? null,
      state_code: profile.value.state_code ?? null,
      state_name: profile.value.state_name ?? '',
      city: profile.value.city ?? '',
      languages: profile.value.languages ?? [],
      bio: profile.value.bio ?? '',
      email_apply_enabled: profile.value.email_apply_enabled ?? false,
      email_apply_message_mode: profile.value.email_apply_message_mode ?? null,
      email_apply_message_template: profile.value.email_apply_message_template ?? '',
      email_apply_resume_mode: profile.value.email_apply_resume_mode ?? null,
      email_apply_resume_id: profile.value.email_apply_resume_id ?? null,
    };
    selectedSkills.value = [...profile.value.skills];
  }
});

let skillTimeout: ReturnType<typeof setTimeout> | null = null;

watch(skillQuery, (q) => {
  if (skillTimeout) clearTimeout(skillTimeout);
  highlightIdx.value = HIGHLIGHT_INDEX_NONE;
  if (!q.trim() || selectedSkills.value.length >= MAX_SKILLS) {
    skillSuggestions.value = [];
    skillDropdownOpen.value = false;
    return;
  }
  skillTimeout = setTimeout(async () => {
    const results = await searchSkills(q);
    skillSuggestions.value = results.filter(
      (r) => !selectedSkills.value.some((s) => s.id === r.id),
    );
    skillDropdownOpen.value = skillSuggestions.value.length > 0;
  }, 200);
});

function addSkill(skill: Skill): void {
  if (selectedSkills.value.length >= MAX_SKILLS) return;
  if (!selectedSkills.value.some((s) => s.id === skill.id)) {
    selectedSkills.value.push(skill);
  }
  skillQuery.value = '';
  skillSuggestions.value = [];
  skillDropdownOpen.value = false;
  highlightIdx.value = HIGHLIGHT_INDEX_NONE;
}

function onSkillKeydown(e: KeyboardEvent): void {
  if (!skillDropdownOpen.value) return;
  if (e.key === 'ArrowDown') {
    e.preventDefault();
    highlightIdx.value = Math.min(highlightIdx.value + 1, skillSuggestions.value.length - 1);
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    highlightIdx.value = Math.max(highlightIdx.value - 1, 0);
  } else if (e.key === 'Enter') {
    e.preventDefault();
    const idx = highlightIdx.value >= 0 ? highlightIdx.value : 0;
    if (skillSuggestions.value[idx]) addSkill(skillSuggestions.value[idx]);
  }
}

function onSkillBlur(): void {
  window.setTimeout(() => { skillDropdownOpen.value = false; }, 150);
}

function removeSkill(id: number): void {
  selectedSkills.value = selectedSkills.value.filter((s) => s.id !== id);
}

function toggleLanguage(locale: Locale): void {
  if (form.value.languages.includes(locale)) {
    form.value.languages = form.value.languages.filter((l) => l !== locale);
  } else {
    form.value.languages = [...form.value.languages, locale];
  }
}

async function onSubmit(): Promise<void> {
  fieldErrors.value = {};
  saveError.value = null;
  savedFlash.value = false;
  try {
    await save({
      desired_role: form.value.desired_role || null,
      seniority: form.value.seniority,
      modality: form.value.modality,
      salary_min: form.value.salary_min,
      salary_max: form.value.salary_max,
      salary_currency: form.value.salary_currency || null,
      country_code: form.value.country_code,
      postal_code: form.value.postal_code,
      state_code: form.value.state_code,
      state_name: form.value.state_name,
      city: form.value.city,
      languages: form.value.languages,
      bio: form.value.bio || null,
      skills: selectedSkills.value.map((s) => s.id),
      email_apply_enabled: form.value.email_apply_enabled,
      email_apply_message_mode: form.value.email_apply_message_mode,
      email_apply_message_template: form.value.email_apply_message_template || null,
      email_apply_resume_mode: form.value.email_apply_resume_mode,
      email_apply_resume_id: form.value.email_apply_resume_id,
    });
    savedFlash.value = true;
    setTimeout(() => (savedFlash.value = false), 3000);
  } catch (e) {
    const err = e as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } };
    if (err.response?.status === 422 && err.response.data?.errors) {
      fieldErrors.value = err.response.data.errors;
    } else {
      saveError.value = t('profile.saveFailed');
    }
  }
}
</script>

<template>
  <div class="lg:flex lg:h-full lg:flex-col">
    <header class="mb-4 lg:mb-3">
      <h1 class="text-2xl font-bold tracking-tight text-ink-900 lg:text-xl">
        {{ t('profile.title') }}
      </h1>
      <p class="mt-0.5 text-xs text-ink-500">
        {{ t('profile.subtitle') }}
      </p>
    </header>

    <form
      class="card space-y-4 p-5 lg:min-h-0 lg:flex-1 lg:overflow-y-auto lg:space-y-3 lg:p-4"
      @submit.prevent="onSubmit"
    >
      <label class="block">
        <span class="label">{{ t('profile.desired_role') }}</span>
        <input
          v-model="form.desired_role"
          type="text"
          :placeholder="t('profile.desired_role_placeholder')"
          class="input mt-1.5"
        >
        <span
          v-if="fieldErrors.desired_role"
          class="mt-1 block text-xs text-red-600"
        >
          {{ fieldErrors.desired_role[0] }}
        </span>
      </label>

      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <label class="block">
          <span class="label">{{ t('profile.seniority') }}</span>
          <select
            v-model="form.seniority"
            class="input mt-1.5"
          >
            <option :value="null">—</option>
            <option
              v-for="s in seniorities"
              :key="s"
              :value="s"
            >{{ t(`seniority.${s}`) }}</option>
          </select>
        </label>

        <label class="block">
          <span class="label">{{ t('profile.modality') }}</span>
          <select
            v-model="form.modality"
            class="input mt-1.5"
          >
            <option :value="null">—</option>
            <option
              v-for="m in modalities"
              :key="m"
              :value="m"
            >{{ t(`modality.${m}`) }}</option>
          </select>
        </label>
      </div>

      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <label class="block">
          <span class="label">{{ t('profile.salary_min') }}</span>
          <input
            v-model.number="form.salary_min"
            type="number"
            min="0"
            class="input mt-1.5"
          >
          <span
            v-if="fieldErrors.salary_min"
            class="mt-1 block text-xs text-red-600"
          >
            {{ fieldErrors.salary_min[0] }}
          </span>
        </label>
        <label class="block">
          <span class="label">{{ t('profile.salary_max') }}</span>
          <input
            v-model.number="form.salary_max"
            type="number"
            min="0"
            class="input mt-1.5"
          >
          <span
            v-if="fieldErrors.salary_max"
            class="mt-1 block text-xs text-red-600"
          >
            {{ fieldErrors.salary_max[0] }}
          </span>
        </label>
      </div>

      <LocationFields
        v-model="locationModel"
        :countries="countriesQuery.data.value ?? []"
        :field-errors="fieldErrors"
      />

      <div>
        <span class="label">{{ t('profile.languages') }}</span>
        <div class="mt-2 flex flex-wrap gap-2">
          <button
            v-for="loc in locales"
            :key="loc"
            type="button"
            :class="[
              'rounded-full border px-3 py-1 text-xs font-medium transition',
              form.languages.includes(loc)
                ? 'border-brand-600 bg-brand-600 text-white shadow-soft'
                : 'border-ink-200 text-ink-700 hover:border-brand-300 hover:text-brand-700',
            ]"
            @click="toggleLanguage(loc)"
          >
            {{ loc }}
          </button>
        </div>
      </div>

      <div>
        <span class="label">{{ t('profile.skills') }}</span>
        <div
          v-if="selectedSkills.length"
          class="mt-2 flex flex-wrap gap-2"
        >
          <span
            v-for="s in selectedSkills"
            :key="s.id"
            class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700 ring-1 ring-inset ring-brand-100"
          >
            {{ s.name }}
            <button
              type="button"
              class="text-brand-500 hover:text-brand-800"
              @click="removeSkill(s.id)"
            >
              ×
            </button>
          </span>
        </div>
        <div class="relative mt-2">
          <input
            v-model="skillQuery"
            type="text"
            autocomplete="off"
            :placeholder="selectedSkills.length >= MAX_SKILLS ? t('profile.skills_max') : t('profile.skills_placeholder')"
            :disabled="selectedSkills.length >= MAX_SKILLS"
            class="input"
            @keydown="onSkillKeydown"
            @blur="onSkillBlur"
          >
          <ul
            v-if="skillDropdownOpen"
            class="absolute left-0 right-0 top-full z-50 mt-1 max-h-48 overflow-auto rounded-lg border border-ink-200 bg-white shadow-card"
          >
            <li
              v-for="(s, i) in skillSuggestions"
              :key="s.id"
              class="cursor-pointer px-3 py-2 text-sm transition"
              :class="i === highlightIdx ? 'bg-brand-50 text-brand-700' : 'text-ink-800 hover:bg-ink-50'"
              @mousedown.prevent="addSkill(s)"
            >
              {{ s.name }}
              <span
                v-if="s.category"
                class="ml-1 text-[10px] text-ink-400"
              >{{ s.category }}</span>
            </li>
          </ul>
        </div>
        <p class="mt-1 text-[10px] text-ink-400">
          {{ selectedSkills.length }}/{{ MAX_SKILLS }}
        </p>
      </div>

      <label class="block">
        <span class="label">{{ t('profile.bio') }}</span>
        <textarea
          v-model="form.bio"
          rows="4"
          maxlength="2000"
          class="input mt-1.5"
        />
      </label>

      <!-- Email Apply Section -->
      <div class="border-t border-ink-200 pt-5">
        <h3 class="text-sm font-semibold text-ink-900">
          {{ t('profile.email_apply') }}
        </h3>
        <p class="mt-0.5 text-xs text-ink-500">
          {{ t('profile.email_apply_desc') }}
        </p>

        <label class="mt-3 inline-flex cursor-pointer items-center gap-2">
          <input
            v-model="form.email_apply_enabled"
            type="checkbox"
            class="h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500"
          >
          <span class="text-sm text-ink-700">{{ t('profile.email_apply_enabled') }}</span>
        </label>

        <div
          v-if="form.email_apply_enabled"
          class="mt-4 space-y-4 rounded-lg border border-ink-200 bg-ink-50 p-4"
        >
          <!-- Message mode -->
          <div>
            <span class="label">{{ t('profile.email_apply_message_mode') }}</span>
            <div class="mt-2 flex gap-4">
              <label class="inline-flex cursor-pointer items-center gap-2">
                <input
                  v-model="form.email_apply_message_mode"
                  type="radio"
                  value="fixed"
                  class="h-4 w-4 border-ink-300 text-brand-600 focus:ring-brand-500"
                >
                <span class="text-sm text-ink-700">{{ t('profile.email_apply_message_fixed') }}</span>
              </label>
              <label class="inline-flex cursor-pointer items-center gap-2">
                <input
                  v-model="form.email_apply_message_mode"
                  type="radio"
                  value="variable"
                  class="h-4 w-4 border-ink-300 text-brand-600 focus:ring-brand-500"
                >
                <span class="text-sm text-ink-700">{{ t('profile.email_apply_message_variable') }}</span>
              </label>
            </div>
          </div>

          <!-- Message template (fixed mode) -->
          <div v-if="form.email_apply_message_mode === 'fixed'">
            <label class="label">{{ t('profile.email_apply_template') }}</label>
            <textarea
              v-model="form.email_apply_message_template"
              rows="4"
              class="input mt-1.5"
            />
            <p class="mt-1 text-[10px] text-ink-400">
              {{ t('profile.email_apply_template_hint') }}
            </p>
          </div>

          <!-- Resume mode -->
          <div>
            <span class="label">{{ t('profile.email_apply_resume_mode') }}</span>
            <div class="mt-2 flex gap-4">
              <label class="inline-flex cursor-pointer items-center gap-2">
                <input
                  v-model="form.email_apply_resume_mode"
                  type="radio"
                  value="fixed"
                  class="h-4 w-4 border-ink-300 text-brand-600 focus:ring-brand-500"
                >
                <span class="text-sm text-ink-700">{{ t('profile.email_apply_resume_fixed') }}</span>
              </label>
              <label class="inline-flex cursor-pointer items-center gap-2">
                <input
                  v-model="form.email_apply_resume_mode"
                  type="radio"
                  value="variable"
                  class="h-4 w-4 border-ink-300 text-brand-600 focus:ring-brand-500"
                >
                <span class="text-sm text-ink-700">{{ t('profile.email_apply_resume_variable') }}</span>
              </label>
            </div>
          </div>

          <!-- Resume select (fixed mode) -->
          <div v-if="form.email_apply_resume_mode === 'fixed'">
            <label class="label">{{ t('profile.email_apply_resume_select') }}</label>
            <select
              v-model="form.email_apply_resume_id"
              class="input mt-1.5"
            >
              <option :value="null">
                —
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
        </div>
      </div>

      <div class="flex items-center justify-between border-t border-ink-200 pt-5">
        <div>
          <p
            v-if="savedFlash"
            class="text-sm font-medium text-emerald-600"
          >
            {{ t('profile.saved') }}
          </p>
          <p
            v-if="saveError"
            class="text-sm text-red-600"
          >
            {{ saveError }}
          </p>
        </div>
        <button
          type="submit"
          :disabled="loading"
          class="btn-primary"
        >
          {{ t('profile.save') }}
        </button>
      </div>
    </form>
  </div>
</template>
