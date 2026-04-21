<script setup lang="ts">
import type { Resume } from '@/shared/api/schemas';
import { dateRange, sectionsByType, str } from './helpers';

defineProps<{ resume: Resume; userName?: string }>();
</script>

<template>
  <article
    class="pdf-page bg-white text-slate-900 grid grid-cols-3"
    style="width: 794px; min-height: 1123px; font-family: 'Inter', 'Helvetica', 'Arial', sans-serif;"
  >
    <aside class="col-span-1 bg-emerald-700 text-white p-8">
      <h1 class="text-2xl font-bold leading-tight">{{ userName ?? resume.title }}</h1>
      <p class="text-sm text-emerald-200 mt-2">{{ resume.title }}</p>

      <section v-if="sectionsByType(resume.sections, 'skill').length" class="mt-8">
        <h2 class="text-xs uppercase tracking-widest font-semibold text-emerald-200 mb-2">Skills</h2>
        <ul class="space-y-1 text-sm">
          <li v-for="sk in sectionsByType(resume.sections, 'skill')" :key="`sk-${sk.order}`">
            <span class="font-medium">{{ str(sk.content, 'name') }}</span>
            <span v-if="str(sk.content, 'level')" class="text-emerald-200 text-xs block">
              {{ str(sk.content, 'level') }}
            </span>
          </li>
        </ul>
      </section>

      <section v-if="sectionsByType(resume.sections, 'language').length" class="mt-6">
        <h2 class="text-xs uppercase tracking-widest font-semibold text-emerald-200 mb-2">Languages</h2>
        <ul class="space-y-1 text-sm">
          <li v-for="lang in sectionsByType(resume.sections, 'language')" :key="`lang-${lang.order}`">
            <span class="font-medium">{{ str(lang.content, 'name') }}</span>
            <span v-if="str(lang.content, 'level')" class="text-emerald-200 text-xs block">
              {{ str(lang.content, 'level') }}
            </span>
          </li>
        </ul>
      </section>
    </aside>

    <main class="col-span-2 p-8">
      <section v-for="sum in sectionsByType(resume.sections, 'summary')" :key="`sum-${sum.order}`" class="mb-6">
        <p class="text-sm leading-relaxed text-slate-700" style="white-space: pre-wrap;">
          {{ str(sum.content, 'text') }}
        </p>
      </section>

      <section v-if="sectionsByType(resume.sections, 'experience').length" class="mb-6">
        <h2 class="text-xs uppercase tracking-widest font-bold text-emerald-700 border-b border-emerald-200 pb-1 mb-3">
          Experience
        </h2>
        <div
          v-for="exp in sectionsByType(resume.sections, 'experience')"
          :key="`exp-${exp.order}`"
          class="mb-4"
        >
          <div class="flex justify-between items-baseline">
            <h3 class="font-semibold text-slate-900">{{ str(exp.content, 'role') }}</h3>
            <span class="text-xs text-slate-500">{{ dateRange(exp.content) }}</span>
          </div>
          <p v-if="str(exp.content, 'company')" class="text-sm text-emerald-700 font-medium">
            {{ str(exp.content, 'company') }}
          </p>
          <p v-if="str(exp.content, 'description')" class="text-sm text-slate-700 mt-1" style="white-space: pre-wrap;">
            {{ str(exp.content, 'description') }}
          </p>
        </div>
      </section>

      <section v-if="sectionsByType(resume.sections, 'education').length" class="mb-6">
        <h2 class="text-xs uppercase tracking-widest font-bold text-emerald-700 border-b border-emerald-200 pb-1 mb-3">
          Education
        </h2>
        <div
          v-for="edu in sectionsByType(resume.sections, 'education')"
          :key="`edu-${edu.order}`"
          class="mb-3"
        >
          <div class="flex justify-between items-baseline">
            <h3 class="font-semibold">{{ str(edu.content, 'degree') }}</h3>
            <span class="text-xs text-slate-500">{{ dateRange(edu.content) }}</span>
          </div>
          <p class="text-sm text-slate-700">
            {{ str(edu.content, 'institution') }}
            <span v-if="str(edu.content, 'field')"> · {{ str(edu.content, 'field') }}</span>
          </p>
        </div>
      </section>

      <section v-if="sectionsByType(resume.sections, 'project').length">
        <h2 class="text-xs uppercase tracking-widest font-bold text-emerald-700 border-b border-emerald-200 pb-1 mb-3">
          Projects
        </h2>
        <div
          v-for="proj in sectionsByType(resume.sections, 'project')"
          :key="`proj-${proj.order}`"
          class="mb-3"
        >
          <h3 class="font-semibold">
            {{ str(proj.content, 'name') }}
            <span v-if="str(proj.content, 'url')" class="font-normal text-emerald-600 text-xs">
              · {{ str(proj.content, 'url') }}
            </span>
          </h3>
          <p v-if="str(proj.content, 'description')" class="text-sm text-slate-700" style="white-space: pre-wrap;">
            {{ str(proj.content, 'description') }}
          </p>
        </div>
      </section>
    </main>
  </article>
</template>
