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

const SECTION_TYPES: ResumeSectionType[] = [
  'summary',
  'experience',
  'education',
  'skill',
  'language',
  'project',
];

function emptyContentFor(type: ResumeSectionType): Record<string, unknown> {
  switch (type) {
    case 'summary':
      return { text: '' };
    case 'experience':
      return { company: '', role: '', startDate: '', endDate: '', current: false, description: '' };
    case 'education':
      return { institution: '', degree: '', field: '', startDate: '', endDate: '' };
    case 'skill':
      return { name: '', level: 'intermediate' };
    case 'language':
      return { name: '', level: 'conversational' };
    case 'project':
      return { name: '', description: '', url: '' };
  }
}

const title = ref('');
const language = ref<Locale>('pt_BR');
const sections = ref<ResumeSection[]>([]);
const saveMessage = ref<string | null>(null);
const addType = ref<ResumeSectionType>('summary');

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

function addSection(): void {
  sections.value.push({
    type: addType.value,
    order: sections.value.length,
    content: emptyContentFor(addType.value),
  });
}

function removeSection(idx: number): void {
  sections.value.splice(idx, 1);
  reindex();
}

function move(idx: number, delta: -1 | 1): void {
  const target = idx + delta;
  if (target < 0 || target >= sections.value.length) return;
  const [item] = sections.value.splice(idx, 1);
  sections.value.splice(target, 0, item);
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
  <div class="mx-auto max-w-3xl space-y-6">
    <button
      type="button"
      class="inline-flex items-center gap-1.5 text-sm font-medium text-ink-500 hover:text-ink-900"
      @click="onCancel"
    >
      <span aria-hidden="true">←</span> {{ t('resumes.back') }}
    </button>

    <p v-if="isEdit && detail.isLoading.value" class="text-ink-500">{{ t('resumes.loading') }}</p>
    <p v-else-if="isEdit && detail.error.value" class="text-red-600" role="alert">
      {{ t('resumes.load_failed') }}
    </p>

    <template v-else>
      <header>
        <h1 class="text-3xl font-bold tracking-tight text-ink-900">
          {{ isEdit ? t('resumes.builder.edit_title') : t('resumes.builder.new_title') }}
        </h1>
      </header>

      <section class="card space-y-4 p-6">
        <label class="block">
          <span class="label">{{ t('resumes.builder.resume_title') }}</span>
          <input
            v-model="title"
            type="text"
            class="input mt-1.5"
            :placeholder="t('resumes.builder.title_placeholder')"
          />
        </label>

        <label class="block max-w-xs">
          <span class="label">{{ t('resumes.language') }}</span>
          <select v-model="language" class="input mt-1.5">
            <option value="pt_BR">pt_BR</option>
            <option value="en">en</option>
            <option value="es">es</option>
          </select>
        </label>
      </section>

      <section class="card p-6">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-lg font-semibold text-ink-900">{{ t('resumes.builder.sections') }}</h2>
          <div class="flex items-center gap-2">
            <select v-model="addType" class="input !py-1.5 text-sm">
              <option v-for="type in SECTION_TYPES" :key="type" :value="type">
                {{ t(`resumes.sections.${type}`) }}
              </option>
            </select>
            <button type="button" class="btn-secondary !py-1.5 text-sm" @click="addSection">
              + {{ t('resumes.builder.add_section') }}
            </button>
          </div>
        </div>

        <p v-if="sections.length === 0" class="text-sm text-ink-500">
          {{ t('resumes.builder.no_sections') }}
        </p>

        <ol v-else class="space-y-4">
          <li
            v-for="(section, idx) in sections"
            :key="idx"
            class="rounded-xl border border-ink-200 bg-ink-50/40 p-5"
          >
            <div class="mb-4 flex items-center justify-between">
              <span class="chip-brand">{{ t(`resumes.sections.${section.type}`) }}</span>
              <div class="flex items-center gap-2 text-xs">
                <button
                  type="button"
                  class="rounded-md p-1.5 text-ink-500 hover:bg-ink-100 hover:text-ink-900 disabled:opacity-30"
                  :disabled="idx === 0"
                  :title="t('resumes.builder.move_up')"
                  @click="move(idx, -1)"
                >
                  ↑
                </button>
                <button
                  type="button"
                  class="rounded-md p-1.5 text-ink-500 hover:bg-ink-100 hover:text-ink-900 disabled:opacity-30"
                  :disabled="idx === sections.length - 1"
                  :title="t('resumes.builder.move_down')"
                  @click="move(idx, 1)"
                >
                  ↓
                </button>
                <button
                  type="button"
                  class="font-medium text-red-600 hover:text-red-800"
                  @click="removeSection(idx)"
                >
                  {{ t('resumes.builder.remove_section') }}
                </button>
              </div>
            </div>

            <!-- summary -->
            <template v-if="section.type === 'summary'">
              <label class="block">
                <span class="label">{{ t('resumes.fields.text') }}</span>
                <textarea
                  :value="contentString(section.content, 'text')"
                  rows="4"
                  class="input mt-1.5"
                  @input="setContent(idx, 'text', ($event.target as HTMLTextAreaElement).value)"
                />
              </label>
            </template>

            <!-- experience -->
            <template v-else-if="section.type === 'experience'">
              <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <label class="block">
                  <span class="label">{{ t('resumes.fields.company') }}</span>
                  <input
                    :value="contentString(section.content, 'company')"
                    type="text"
                    class="input mt-1.5"
                    @input="setContent(idx, 'company', ($event.target as HTMLInputElement).value)"
                  />
                </label>
                <label class="block">
                  <span class="label">{{ t('resumes.fields.role') }}</span>
                  <input
                    :value="contentString(section.content, 'role')"
                    type="text"
                    class="input mt-1.5"
                    @input="setContent(idx, 'role', ($event.target as HTMLInputElement).value)"
                  />
                </label>
                <label class="block">
                  <span class="label">{{ t('resumes.fields.start_date') }}</span>
                  <input
                    :value="contentString(section.content, 'startDate')"
                    type="text"
                    placeholder="2022-01"
                    class="input mt-1.5"
                    @input="setContent(idx, 'startDate', ($event.target as HTMLInputElement).value)"
                  />
                </label>
                <label class="block">
                  <span class="label">{{ t('resumes.fields.end_date') }}</span>
                  <input
                    :value="contentString(section.content, 'endDate')"
                    type="text"
                    placeholder="2024-06"
                    :disabled="contentBool(section.content, 'current')"
                    class="input mt-1.5"
                    @input="setContent(idx, 'endDate', ($event.target as HTMLInputElement).value)"
                  />
                </label>
              </div>
              <label class="mt-3 inline-flex items-center gap-2 text-sm text-ink-700">
                <input
                  type="checkbox"
                  class="h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500"
                  :checked="contentBool(section.content, 'current')"
                  @change="setContent(idx, 'current', ($event.target as HTMLInputElement).checked)"
                />
                {{ t('resumes.fields.current') }}
              </label>
              <label class="mt-3 block">
                <span class="label">{{ t('resumes.fields.description') }}</span>
                <textarea
                  :value="contentString(section.content, 'description')"
                  rows="3"
                  class="input mt-1.5"
                  @input="
                    setContent(idx, 'description', ($event.target as HTMLTextAreaElement).value)
                  "
                />
              </label>
            </template>

            <!-- education -->
            <template v-else-if="section.type === 'education'">
              <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <label class="block">
                  <span class="label">{{ t('resumes.fields.institution') }}</span>
                  <input
                    :value="contentString(section.content, 'institution')"
                    type="text"
                    class="input mt-1.5"
                    @input="
                      setContent(idx, 'institution', ($event.target as HTMLInputElement).value)
                    "
                  />
                </label>
                <label class="block">
                  <span class="label">{{ t('resumes.fields.degree') }}</span>
                  <input
                    :value="contentString(section.content, 'degree')"
                    type="text"
                    class="input mt-1.5"
                    @input="setContent(idx, 'degree', ($event.target as HTMLInputElement).value)"
                  />
                </label>
                <label class="block">
                  <span class="label">{{ t('resumes.fields.field') }}</span>
                  <input
                    :value="contentString(section.content, 'field')"
                    type="text"
                    class="input mt-1.5"
                    @input="setContent(idx, 'field', ($event.target as HTMLInputElement).value)"
                  />
                </label>
                <div class="grid grid-cols-2 gap-3">
                  <label class="block">
                    <span class="label">{{ t('resumes.fields.start_date') }}</span>
                    <input
                      :value="contentString(section.content, 'startDate')"
                      type="text"
                      placeholder="2018-03"
                      class="input mt-1.5"
                      @input="
                        setContent(idx, 'startDate', ($event.target as HTMLInputElement).value)
                      "
                    />
                  </label>
                  <label class="block">
                    <span class="label">{{ t('resumes.fields.end_date') }}</span>
                    <input
                      :value="contentString(section.content, 'endDate')"
                      type="text"
                      placeholder="2022-12"
                      class="input mt-1.5"
                      @input="
                        setContent(idx, 'endDate', ($event.target as HTMLInputElement).value)
                      "
                    />
                  </label>
                </div>
              </div>
            </template>

            <!-- skill / language -->
            <template v-else-if="section.type === 'skill' || section.type === 'language'">
              <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <label class="block">
                  <span class="label">{{ t('resumes.fields.name') }}</span>
                  <input
                    :value="contentString(section.content, 'name')"
                    type="text"
                    class="input mt-1.5"
                    @input="setContent(idx, 'name', ($event.target as HTMLInputElement).value)"
                  />
                </label>
                <label class="block">
                  <span class="label">{{ t('resumes.fields.level') }}</span>
                  <input
                    :value="contentString(section.content, 'level')"
                    type="text"
                    class="input mt-1.5"
                    @input="setContent(idx, 'level', ($event.target as HTMLInputElement).value)"
                  />
                </label>
              </div>
            </template>

            <!-- project -->
            <template v-else-if="section.type === 'project'">
              <label class="block">
                <span class="label">{{ t('resumes.fields.name') }}</span>
                <input
                  :value="contentString(section.content, 'name')"
                  type="text"
                  class="input mt-1.5"
                  @input="setContent(idx, 'name', ($event.target as HTMLInputElement).value)"
                />
              </label>
              <label class="mt-3 block">
                <span class="label">{{ t('resumes.fields.url') }}</span>
                <input
                  :value="contentString(section.content, 'url')"
                  type="url"
                  class="input mt-1.5"
                  @input="setContent(idx, 'url', ($event.target as HTMLInputElement).value)"
                />
              </label>
              <label class="mt-3 block">
                <span class="label">{{ t('resumes.fields.description') }}</span>
                <textarea
                  :value="contentString(section.content, 'description')"
                  rows="3"
                  class="input mt-1.5"
                  @input="
                    setContent(idx, 'description', ($event.target as HTMLTextAreaElement).value)
                  "
                />
              </label>
            </template>
          </li>
        </ol>
      </section>

      <div class="flex items-center gap-3">
        <button
          type="button"
          class="btn-primary"
          :disabled="save.isPending.value"
          @click="onSave"
        >
          {{ save.isPending.value ? t('resumes.builder.saving') : t('resumes.builder.save') }}
        </button>
        <button type="button" class="btn-secondary" @click="onCancel">
          {{ t('resumes.builder.cancel') }}
        </button>
        <span v-if="saveMessage" class="text-xs text-ink-500">{{ saveMessage }}</span>
      </div>
    </template>
  </div>
</template>
