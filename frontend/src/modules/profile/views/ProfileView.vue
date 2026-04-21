<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useProfile, searchSkills } from '../composables/useProfile';
import type { Skill, Seniority, Modality, Locale } from '@/shared/api/schemas';

const { t } = useI18n();
const { profile, loading, fetch, save } = useProfile();

const form = ref({
  desired_role: '',
  seniority: null as Seniority | null,
  modality: null as Modality | null,
  salary_min: null as number | null,
  salary_max: null as number | null,
  salary_currency: 'BRL',
  location: '',
  languages: [] as Locale[],
  bio: '',
});
const selectedSkills = ref<Skill[]>([]);
const skillQuery = ref('');
const skillSuggestions = ref<Skill[]>([]);
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
      location: profile.value.location ?? '',
      languages: profile.value.languages ?? [],
      bio: profile.value.bio ?? '',
    };
    selectedSkills.value = [...profile.value.skills];
  }
});

let skillTimeout: ReturnType<typeof setTimeout> | null = null;
watch(skillQuery, (q) => {
  if (skillTimeout) clearTimeout(skillTimeout);
  if (!q.trim()) {
    skillSuggestions.value = [];
    return;
  }
  skillTimeout = setTimeout(async () => {
    skillSuggestions.value = await searchSkills(q);
  }, 200);
});

function addSkill(skill: Skill): void {
  if (!selectedSkills.value.some((s) => s.id === skill.id)) {
    selectedSkills.value.push(skill);
  }
  skillQuery.value = '';
  skillSuggestions.value = [];
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
      location: form.value.location || null,
      languages: form.value.languages,
      bio: form.value.bio || null,
      skills: selectedSkills.value.map((s) => s.id),
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
  <div class="max-w-3xl">
    <header class="mb-6">
      <h1 class="text-3xl font-bold tracking-tight text-ink-900">{{ t('profile.title') }}</h1>
      <p class="mt-1 text-ink-500">{{ t('profile.subtitle') }}</p>
    </header>

    <form class="card space-y-6 p-6" @submit.prevent="onSubmit">
      <label class="block">
        <span class="label">{{ t('profile.desired_role') }}</span>
        <input
          v-model="form.desired_role"
          type="text"
          :placeholder="t('profile.desired_role_placeholder')"
          class="input mt-1.5"
        />
        <span v-if="fieldErrors.desired_role" class="mt-1 block text-xs text-red-600">
          {{ fieldErrors.desired_role[0] }}
        </span>
      </label>

      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <label class="block">
          <span class="label">{{ t('profile.seniority') }}</span>
          <select v-model="form.seniority" class="input mt-1.5">
            <option :value="null">—</option>
            <option v-for="s in seniorities" :key="s" :value="s">{{ t(`seniority.${s}`) }}</option>
          </select>
        </label>

        <label class="block">
          <span class="label">{{ t('profile.modality') }}</span>
          <select v-model="form.modality" class="input mt-1.5">
            <option :value="null">—</option>
            <option v-for="m in modalities" :key="m" :value="m">{{ t(`modality.${m}`) }}</option>
          </select>
        </label>
      </div>

      <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <label class="block">
          <span class="label">{{ t('profile.salary_min') }}</span>
          <input v-model.number="form.salary_min" type="number" min="0" class="input mt-1.5" />
          <span v-if="fieldErrors.salary_min" class="mt-1 block text-xs text-red-600">
            {{ fieldErrors.salary_min[0] }}
          </span>
        </label>
        <label class="block">
          <span class="label">{{ t('profile.salary_max') }}</span>
          <input v-model.number="form.salary_max" type="number" min="0" class="input mt-1.5" />
          <span v-if="fieldErrors.salary_max" class="mt-1 block text-xs text-red-600">
            {{ fieldErrors.salary_max[0] }}
          </span>
        </label>
        <label class="block">
          <span class="label">{{ t('profile.salary_currency') }}</span>
          <input
            v-model="form.salary_currency"
            type="text"
            maxlength="3"
            class="input mt-1.5 uppercase"
          />
        </label>
      </div>

      <label class="block">
        <span class="label">{{ t('profile.location') }}</span>
        <input v-model="form.location" type="text" class="input mt-1.5" />
      </label>

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
        <div class="mt-2 flex flex-wrap gap-2">
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
            :placeholder="t('profile.skills_placeholder')"
            class="input"
          />
          <ul
            v-if="skillSuggestions.length"
            class="absolute z-10 mt-1 max-h-48 w-full overflow-auto rounded-lg border border-ink-200 bg-white shadow-card"
          >
            <li
              v-for="s in skillSuggestions"
              :key="s.id"
              class="cursor-pointer px-3 py-2 text-sm hover:bg-brand-50 hover:text-brand-700"
              @click="addSkill(s)"
            >
              {{ s.name }}
            </li>
          </ul>
        </div>
      </div>

      <label class="block">
        <span class="label">{{ t('profile.bio') }}</span>
        <textarea v-model="form.bio" rows="4" maxlength="2000" class="input mt-1.5" />
      </label>

      <div class="flex items-center justify-between border-t border-ink-200 pt-5">
        <div>
          <p v-if="savedFlash" class="text-sm font-medium text-emerald-600">
            {{ t('profile.saved') }}
          </p>
          <p v-if="saveError" class="text-sm text-red-600">{{ saveError }}</p>
        </div>
        <button type="submit" :disabled="loading" class="btn-primary">
          {{ t('profile.save') }}
        </button>
      </div>
    </form>
  </div>
</template>
