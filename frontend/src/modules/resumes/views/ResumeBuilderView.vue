<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import type { Locale, ResumeSection, ResumeSectionType } from '@/shared/api/schemas';
import { useResumeDetail } from '@/modules/resumes/composables/useResumeDetail';
import { useSaveResume } from '@/modules/resumes/composables/useSaveResume';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();

const routeId = computed(() => {
  const raw = route.params.id;
  const n = Array.isArray(raw) ? Number(raw[0]) : Number(raw);
  return Number.isFinite(n) && n > 0 ? n : 0;
});
const isEdit = computed(() => routeId.value > 0);

const detail = useResumeDetail(routeId);
const save = useSaveResume();

type SectionOption = { type: ResumeSectionType; icon: string };

const SECTION_OPTIONS: SectionOption[] = [
  { type: 'summary',    icon: 'M4 6h16M4 12h16M4 18h7' },
  { type: 'experience', icon: 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m6 7v-1a1 1 0 00-1-1h-4a1 1 0 00-1 1v1M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z' },
  { type: 'education',  icon: 'M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422A12.083 12.083 0 0121 12.513c0 1.2-.4 2.3-1.1 3.2M12 14l-6.16-3.422A12.083 12.083 0 003 12.513c0 1.2.4 2.3 1.1 3.2M12 14v7' },
  { type: 'skill',      icon: 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z' },
  { type: 'language',   icon: 'M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129' },
  { type: 'project',    icon: 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4' },
  { type: 'contact',    icon: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z' },
];

const SKILL_LEVELS = ['beginner', 'intermediate', 'advanced', 'expert'];
const LANGUAGE_LEVELS = ['basic', 'conversational', 'fluent', 'native'];

function emptyContentFor(type: ResumeSectionType): Record<string, unknown> {
  switch (type) {
    case 'summary':
      return { text: '' };
    case 'experience':
      return { company: '', role: '', startDate: '', endDate: '', current: false, description: '' };
    case 'education':
      return { institution: '', degree: '', startDate: '', endDate: '' };
    case 'skill':
      return { name: '', level: 'intermediate' };
    case 'language':
      return { name: '', level: 'conversational' };
    case 'project':
      return { name: '', description: '', url: '' };
    case 'contact':
      return { email: '', phone: '', linkedin: '', github: '', website: '', address: '' };
  }
}

const title = ref('');
const language = ref<Locale>('pt_BR');
const saveMessage = ref<string | null>(null);

function defaultSections(): ResumeSection[] {
  return [
    { type: 'contact', order: 0, content: { email: '', phone: '', linkedin: '', github: '', website: '', address: '' } },
    { type: 'summary', order: 1, content: { text: '' } },
    { type: 'experience', order: 2, content: { company: '', role: '', startDate: '', endDate: '', current: false, description: '' } },
    { type: 'experience', order: 3, content: { company: '', role: '', startDate: '', endDate: '', current: false, description: '' } },
    { type: 'education', order: 4, content: { institution: '', degree: '', startDate: '', endDate: '' } },
    { type: 'skill', order: 5, content: { name: '', level: 'intermediate' } },
    { type: 'skill', order: 6, content: { name: '', level: 'intermediate' } },
    { type: 'skill', order: 7, content: { name: '', level: 'intermediate' } },
    { type: 'skill', order: 8, content: { name: '', level: 'intermediate' } },
    { type: 'skill', order: 9, content: { name: '', level: 'intermediate' } },
    { type: 'language', order: 10, content: { name: '', level: 'conversational' } },
    { type: 'language', order: 11, content: { name: '', level: 'conversational' } },
  ];
}

const sections = ref<ResumeSection[]>(isEdit.value ? [] : defaultSections());

function clearSections(): void {
  sections.value = [];
}

function restoreDefaults(): void {
  sections.value = defaultSections();
}

watch(
  () => detail.data.value,
  (resume) => {
    if (resume) {
      title.value = resume.title;
      language.value = resume.language;
      sections.value = [...resume.sections].sort((a, b) => a.order - b.order);
    }
  },
  { immediate: true }
);

type GroupedSection = { type: ResumeSectionType; indices: number[] };

const groupedSections = computed<GroupedSection[]>(() => {
  const groups: GroupedSection[] = [];
  const seen = new Set<ResumeSectionType>();
  for (let i = 0; i < sections.value.length; i++) {
    const s = sections.value[i];
    if (!seen.has(s.type)) {
      seen.add(s.type);
      groups.push({ type: s.type, indices: [] });
    }
    groups.find((g) => g.type === s.type)!.indices.push(i);
  }
  return groups;
});

function addSection(type: ResumeSectionType): void {
  sections.value.push({
    type,
    order: sections.value.length,
    content: emptyContentFor(type),
  });
}

function hasSection(type: ResumeSectionType): boolean {
  return sections.value.some((s) => s.type === type);
}

function removeEntry(idx: number): void {
  sections.value.splice(idx, 1);
  reindex();
}

function reindex(): void {
  sections.value.forEach((s, i) => (s.order = i));
}

function contentString(content: Record<string, unknown>, key: string): string {
  const v = content[key];
  return typeof v === 'string' ? v : '';
}

function contentBool(content: Record<string, unknown>, key: string): boolean {
  return content[key] === true;
}

function setContent(idx: number, key: string, value: unknown): void {
  sections.value[idx].content = { ...sections.value[idx].content, [key]: value };
}

function sectionCount(type: ResumeSectionType): number {
  return sections.value.filter((s) => s.type === type).length;
}

async function onSave(): Promise<void> {
  saveMessage.value = null;
  try {
    const resume = await save.mutateAsync({
      id: isEdit.value ? routeId.value : undefined,
      title: title.value,
      language: language.value,
      sections: sections.value,
    });
    saveMessage.value = t('resumes.builder.saved');
    if (!isEdit.value) {
      router.replace({ name: 'resume-edit', params: { id: resume.id } });
    }
  } catch {
    saveMessage.value = t('resumes.builder.save_failed');
  }
}

function onCancel(): void {
  router.push({ name: 'resumes' });
}
</script>

<template>
  <div class="lg:flex lg:h-full lg:flex-col">
    <!-- Top bar -->
    <div class="mb-3 flex items-center justify-between lg:mb-2">
      <div class="flex items-center gap-4">
        <button
          type="button"
          class="inline-flex items-center gap-1.5 text-sm font-medium text-ink-500 hover:text-ink-900"
          @click="onCancel"
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
              d="M15 19l-7-7 7-7"
            />
          </svg>
          {{ t('resumes.back') }}
        </button>
        <h1 class="text-lg font-bold tracking-tight text-ink-900">
          {{ isEdit ? t('resumes.builder.edit_title') : t('resumes.builder.new_title') }}
        </h1>
      </div>
      <div class="flex items-center gap-2">
        <span
          v-if="saveMessage"
          class="text-xs text-ink-500"
        >{{ saveMessage }}</span>
        <button
          type="button"
          class="btn-secondary !py-1.5 text-sm"
          @click="onCancel"
        >
          {{ t('resumes.builder.cancel') }}
        </button>
        <button
          type="button"
          class="btn-primary !py-1.5 text-sm"
          :disabled="save.isPending.value"
          @click="onSave"
        >
          {{ save.isPending.value ? t('resumes.builder.saving') : t('resumes.builder.save') }}
        </button>
      </div>
    </div>

    <p
      v-if="isEdit && detail.isLoading.value"
      class="text-ink-500"
    >
      {{ t('resumes.loading') }}
    </p>
    <p
      v-else-if="isEdit && detail.error.value"
      class="text-red-600"
      role="alert"
    >
      {{ t('resumes.load_failed') }}
    </p>

    <template v-else>
      <div class="flex gap-4 lg:min-h-0 lg:flex-1">
        <!-- Sidebar -->
        <aside class="hidden w-56 shrink-0 flex-col gap-3 lg:flex">
          <div class="card p-3 space-y-2">
            <label class="block">
              <span class="text-xs font-semibold text-ink-600">{{ t('resumes.builder.resume_title') }}</span>
              <input
                v-model="title"
                type="text"
                class="input mt-1 !py-1.5 text-sm"
                :placeholder="t('resumes.builder.title_placeholder')"
              >
            </label>
            <label class="block">
              <span class="text-xs font-semibold text-ink-600">{{ t('resumes.language') }}</span>
              <select
                v-model="language"
                class="input mt-1 !py-1.5 text-sm"
              >
                <option value="pt_BR">Português</option>
                <option value="en">English</option>
                <option value="es">Español</option>
              </select>
            </label>
          </div>

          <div class="card p-3">
            <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-ink-400">
              {{ t('resumes.builder.add_section') }}
            </p>
            <div class="space-y-1">
              <button
                v-for="opt in SECTION_OPTIONS"
                :key="opt.type"
                type="button"
                class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-sm font-medium transition"
                :class="hasSection(opt.type)
                  ? 'bg-brand-50 text-brand-700'
                  : 'text-ink-700 hover:bg-brand-50 hover:text-brand-700'"
                @click="addSection(opt.type)"
              >
                <svg
                  class="h-4 w-4 shrink-0"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.75"
                    :d="opt.icon"
                  />
                </svg>
                <span class="flex-1">{{ t(`resumes.sections.${opt.type}`) }}</span>
                <span
                  v-if="sectionCount(opt.type) > 0"
                  class="grid h-5 w-5 place-items-center rounded-full bg-brand-100 text-[10px] font-bold text-brand-700"
                >
                  {{ sectionCount(opt.type) }}
                </span>
              </button>
            </div>
            <button
              v-if="sections.length > 0"
              type="button"
              class="mt-3 flex w-full items-center justify-center gap-1.5 rounded-lg border border-ink-200 px-2.5 py-1.5 text-xs font-medium text-ink-500 transition hover:border-red-300 hover:text-red-600"
              @click="clearSections"
            >
              <svg
                class="h-3.5 w-3.5"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                />
              </svg>
              {{ t('resumes.builder.clear_all') }}
            </button>
          </div>
        </aside>

        <!-- Mobile bottom bar -->
        <div class="fixed bottom-4 left-4 right-4 z-10 flex gap-1.5 overflow-x-auto rounded-xl border border-ink-300 bg-white p-2 shadow-card lg:hidden">
          <button
            v-for="opt in SECTION_OPTIONS"
            :key="opt.type"
            type="button"
            class="flex shrink-0 items-center gap-1.5 rounded-lg bg-ink-50 px-3 py-2 text-xs font-medium text-ink-700 transition active:bg-brand-50 active:text-brand-700"
            @click="addSection(opt.type)"
          >
            <span>+</span>
            {{ t(`resumes.sections.${opt.type}`) }}
          </button>
        </div>

        <!-- Main content -->
        <div class="min-w-0 flex-1 lg:min-h-0 lg:overflow-y-auto pb-20 lg:pb-0">
          <!-- Mobile title/language -->
          <div class="card mb-3 space-y-3 p-4 lg:hidden">
            <label class="block">
              <span class="label">{{ t('resumes.builder.resume_title') }}</span>
              <input
                v-model="title"
                type="text"
                class="input mt-1.5"
                :placeholder="t('resumes.builder.title_placeholder')"
              >
            </label>
            <label class="block">
              <span class="label">{{ t('resumes.language') }}</span>
              <select
                v-model="language"
                class="input mt-1.5"
              >
                <option value="pt_BR">Português</option>
                <option value="en">English</option>
                <option value="es">Español</option>
              </select>
            </label>
          </div>

          <!-- Empty state -->
          <div
            v-if="sections.length === 0"
            class="flex h-full items-center justify-center rounded-xl border-2 border-dashed border-ink-300 bg-white/50"
          >
            <div class="text-center py-16">
              <svg
                class="mx-auto h-12 w-12 text-ink-300"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="1.5"
                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                />
              </svg>
              <p class="mt-3 text-sm font-medium text-ink-500">
                {{ t('resumes.builder.no_sections') }}
              </p>
              <p class="mt-1 text-xs text-ink-400">
                {{ t('resumes.builder.no_sections_hint') }}
              </p>
              <button
                type="button"
                class="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-brand-50 px-3 py-1.5 text-xs font-medium text-brand-700 transition hover:bg-brand-100"
                @click="restoreDefaults"
              >
                <svg
                  class="h-3.5 w-3.5"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                  />
                </svg>
                {{ t('resumes.builder.restore_default') }}
              </button>
            </div>
          </div>

          <!-- Grouped sections -->
          <div
            v-else
            class="space-y-3"
          >
            <div
              v-for="group in groupedSections"
              :key="group.type"
              class="card p-4"
            >
              <!-- Group header -->
              <div class="mb-3 flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <span class="chip-brand !py-0.5">{{ t(`resumes.sections.${group.type}`) }}</span>
                  <span class="text-[10px] text-ink-400">{{ group.indices.length }} {{ group.indices.length === 1 ? 'item' : 'itens' }}</span>
                </div>
                <button
                  type="button"
                  class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-brand-700 transition hover:bg-brand-50"
                  @click="addSection(group.type)"
                >
                  <svg
                    class="h-3.5 w-3.5"
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
                  Adicionar
                </button>
              </div>

              <!-- Entries -->
              <div class="space-y-3">
                <div
                  v-for="(idx, entryIdx) in group.indices"
                  :key="idx"
                  class="relative rounded-lg border border-ink-200 bg-ink-50/50 p-3"
                >
                  <!-- Entry remove button -->
                  <button
                    type="button"
                    class="absolute right-2 top-2 rounded p-1 text-ink-300 hover:bg-red-50 hover:text-red-500"
                    @click="removeEntry(idx)"
                  >
                    <svg
                      class="h-3.5 w-3.5"
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

                  <!-- Entry number -->
                  <span
                    v-if="group.indices.length > 1"
                    class="mb-2 inline-block text-[10px] font-bold text-ink-400"
                  >
                    #{{ entryIdx + 1 }}
                  </span>

                  <!-- summary -->
                  <template v-if="sections[idx].type === 'summary'">
                    <label class="block">
                      <span class="label text-xs">{{ t('resumes.fields.text') }}</span>
                      <textarea
                        :value="contentString(sections[idx].content, 'text')"
                        rows="3"
                        class="input mt-1"
                        @input="setContent(idx, 'text', ($event.target as HTMLTextAreaElement).value)"
                      />
                    </label>
                  </template>

                  <!-- experience -->
                  <template v-else-if="sections[idx].type === 'experience'">
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                      <label class="block">
                        <span class="label text-xs">{{ t('resumes.fields.company') }}</span>
                        <input
                          :value="contentString(sections[idx].content, 'company')"
                          type="text"
                          class="input mt-1"
                          @input="setContent(idx, 'company', ($event.target as HTMLInputElement).value)"
                        >
                      </label>
                      <label class="block">
                        <span class="label text-xs">{{ t('resumes.fields.role') }}</span>
                        <input
                          :value="contentString(sections[idx].content, 'role')"
                          type="text"
                          class="input mt-1"
                          @input="setContent(idx, 'role', ($event.target as HTMLInputElement).value)"
                        >
                      </label>
                      <label class="block">
                        <span class="label text-xs">{{ t('resumes.fields.start_date') }}</span>
                        <input
                          :value="contentString(sections[idx].content, 'startDate')"
                          type="text"
                          placeholder="2022-01"
                          class="input mt-1"
                          @input="setContent(idx, 'startDate', ($event.target as HTMLInputElement).value)"
                        >
                      </label>
                      <label class="block">
                        <span class="label text-xs">{{ t('resumes.fields.end_date') }}</span>
                        <input
                          :value="contentString(sections[idx].content, 'endDate')"
                          type="text"
                          placeholder="2024-06"
                          :disabled="contentBool(sections[idx].content, 'current')"
                          class="input mt-1"
                          @input="setContent(idx, 'endDate', ($event.target as HTMLInputElement).value)"
                        >
                      </label>
                    </div>
                    <label class="mt-2 inline-flex items-center gap-2 text-xs text-ink-700">
                      <input
                        type="checkbox"
                        class="h-3.5 w-3.5 rounded border-ink-300 text-brand-600 focus:ring-brand-500"
                        :checked="contentBool(sections[idx].content, 'current')"
                        @change="setContent(idx, 'current', ($event.target as HTMLInputElement).checked)"
                      >
                      {{ t('resumes.fields.current') }}
                    </label>
                    <label class="mt-2 block">
                      <span class="label text-xs">{{ t('resumes.fields.description') }}</span>
                      <textarea
                        :value="contentString(sections[idx].content, 'description')"
                        rows="2"
                        class="input mt-1"
                        @input="setContent(idx, 'description', ($event.target as HTMLTextAreaElement).value)"
                      />
                    </label>
                  </template>

                  <!-- education -->
                  <template v-else-if="sections[idx].type === 'education'">
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                      <label class="block">
                        <span class="label text-xs">{{ t('resumes.fields.institution') }}</span>
                        <input
                          :value="contentString(sections[idx].content, 'institution')"
                          type="text"
                          class="input mt-1"
                          @input="setContent(idx, 'institution', ($event.target as HTMLInputElement).value)"
                        >
                      </label>
                      <label class="block">
                        <span class="label text-xs">{{ t('resumes.fields.degree') }}</span>
                        <input
                          :value="contentString(sections[idx].content, 'degree')"
                          type="text"
                          class="input mt-1"
                          @input="setContent(idx, 'degree', ($event.target as HTMLInputElement).value)"
                        >
                      </label>
                      <label class="block">
                        <span class="label text-xs">{{ t('resumes.fields.start_date') }}</span>
                        <input
                          :value="contentString(sections[idx].content, 'startDate')"
                          type="text"
                          placeholder="2018-03"
                          class="input mt-1"
                          @input="setContent(idx, 'startDate', ($event.target as HTMLInputElement).value)"
                        >
                      </label>
                      <label class="block">
                        <span class="label text-xs">{{ t('resumes.fields.end_date') }}</span>
                        <input
                          :value="contentString(sections[idx].content, 'endDate')"
                          type="text"
                          placeholder="2022-12"
                          class="input mt-1"
                          @input="setContent(idx, 'endDate', ($event.target as HTMLInputElement).value)"
                        >
                      </label>
                    </div>
                  </template>

                  <!-- skill -->
                  <template v-else-if="sections[idx].type === 'skill'">
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                      <label class="block">
                        <span class="label text-xs">{{ t('resumes.fields.name') }}</span>
                        <input
                          :value="contentString(sections[idx].content, 'name')"
                          type="text"
                          class="input mt-1"
                          @input="setContent(idx, 'name', ($event.target as HTMLInputElement).value)"
                        >
                      </label>
                      <label class="block">
                        <span class="label text-xs">{{ t('resumes.fields.level') }}</span>
                        <select
                          :value="contentString(sections[idx].content, 'level')"
                          class="input mt-1"
                          @change="setContent(idx, 'level', ($event.target as HTMLSelectElement).value)"
                        >
                          <option
                            v-for="lvl in SKILL_LEVELS"
                            :key="lvl"
                            :value="lvl"
                          >{{ t(`resumes.levels.${lvl}`) }}</option>
                        </select>
                      </label>
                    </div>
                  </template>

                  <!-- language -->
                  <template v-else-if="sections[idx].type === 'language'">
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                      <label class="block">
                        <span class="label text-xs">{{ t('resumes.fields.name') }}</span>
                        <input
                          :value="contentString(sections[idx].content, 'name')"
                          type="text"
                          class="input mt-1"
                          @input="setContent(idx, 'name', ($event.target as HTMLInputElement).value)"
                        >
                      </label>
                      <label class="block">
                        <span class="label text-xs">{{ t('resumes.fields.level') }}</span>
                        <select
                          :value="contentString(sections[idx].content, 'level')"
                          class="input mt-1"
                          @change="setContent(idx, 'level', ($event.target as HTMLSelectElement).value)"
                        >
                          <option
                            v-for="lvl in LANGUAGE_LEVELS"
                            :key="lvl"
                            :value="lvl"
                          >{{ t(`resumes.levels.${lvl}`) }}</option>
                        </select>
                      </label>
                    </div>
                  </template>

                  <!-- project -->
                  <template v-else-if="sections[idx].type === 'project'">
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                      <label class="block">
                        <span class="label text-xs">{{ t('resumes.fields.name') }}</span>
                        <input
                          :value="contentString(sections[idx].content, 'name')"
                          type="text"
                          class="input mt-1"
                          @input="setContent(idx, 'name', ($event.target as HTMLInputElement).value)"
                        >
                      </label>
                      <label class="block">
                        <span class="label text-xs">{{ t('resumes.fields.url') }}</span>
                        <input
                          :value="contentString(sections[idx].content, 'url')"
                          type="url"
                          class="input mt-1"
                          @input="setContent(idx, 'url', ($event.target as HTMLInputElement).value)"
                        >
                      </label>
                    </div>
                    <label class="mt-2 block">
                      <span class="label text-xs">{{ t('resumes.fields.description') }}</span>
                      <textarea
                        :value="contentString(sections[idx].content, 'description')"
                        rows="2"
                        class="input mt-1"
                        @input="setContent(idx, 'description', ($event.target as HTMLTextAreaElement).value)"
                      />
                    </label>
                  </template>

                  <!-- contact -->
                  <template v-else-if="sections[idx].type === 'contact'">
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                      <label class="block">
                        <span class="label text-xs">{{ t('resumes.fields.contact_email') }}</span>
                        <input
                          :value="contentString(sections[idx].content, 'email')"
                          type="email"
                          class="input mt-1"
                          @input="setContent(idx, 'email', ($event.target as HTMLInputElement).value)"
                        >
                      </label>
                      <label class="block">
                        <span class="label text-xs">{{ t('resumes.fields.phone') }}</span>
                        <input
                          :value="contentString(sections[idx].content, 'phone')"
                          type="tel"
                          class="input mt-1"
                          @input="setContent(idx, 'phone', ($event.target as HTMLInputElement).value)"
                        >
                      </label>
                      <label class="block">
                        <span class="label text-xs">LinkedIn</span>
                        <input
                          :value="contentString(sections[idx].content, 'linkedin')"
                          type="url"
                          class="input mt-1"
                          placeholder="https://linkedin.com/in/..."
                          @input="setContent(idx, 'linkedin', ($event.target as HTMLInputElement).value)"
                        >
                      </label>
                      <label class="block">
                        <span class="label text-xs">GitHub</span>
                        <input
                          :value="contentString(sections[idx].content, 'github')"
                          type="url"
                          class="input mt-1"
                          placeholder="https://github.com/..."
                          @input="setContent(idx, 'github', ($event.target as HTMLInputElement).value)"
                        >
                      </label>
                      <label class="block">
                        <span class="label text-xs">{{ t('resumes.fields.website') }}</span>
                        <input
                          :value="contentString(sections[idx].content, 'website')"
                          type="url"
                          class="input mt-1"
                          @input="setContent(idx, 'website', ($event.target as HTMLInputElement).value)"
                        >
                      </label>
                      <label class="block">
                        <span class="label text-xs">{{ t('resumes.fields.address') }}</span>
                        <input
                          :value="contentString(sections[idx].content, 'address')"
                          type="text"
                          class="input mt-1"
                          @input="setContent(idx, 'address', ($event.target as HTMLInputElement).value)"
                        >
                      </label>
                    </div>
                  </template>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
