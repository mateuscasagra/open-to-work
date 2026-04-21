<script setup lang="ts">
import type { Resume } from '@/shared/api/schemas';
import { dateRange, sectionsByType, str } from './helpers';

defineProps<{ resume: Resume; userName?: string }>();
</script>

<template>
  <article
    class="pdf-page bg-white text-slate-900"
    style="width: 794px; min-height: 1123px; padding: 64px 72px; font-family: 'Georgia', 'Times New Roman', serif;"
  >
    <header class="border-b border-slate-300 pb-4 mb-6">
      <h1 class="text-3xl font-bold tracking-tight">{{ userName ?? resume.title }}</h1>
      <p class="text-sm text-slate-600 mt-1">{{ resume.title }}</p>
    </header>

    <section v-for="sum in sectionsByType(resume.sections, 'summary')" :key="`sum-${sum.order}`" class="mb-5">
      <p class="text-sm leading-relaxed text-slate-800" style="white-space: pre-wrap;">
        {{ str(sum.content, 'text') }}
      </p>
    </section>

    <section v-if="sectionsByType(resume.sections, 'experience').length" class="mb-5">
      <h2 class="text-xs uppercase tracking-widest font-bold text-slate-500 mb-2">Experience</h2>
      <div
        v-for="exp in sectionsByType(resume.sections, 'experience')"
        :key="`exp-${exp.order}`"
        class="mb-3"
      >
        <div class="flex justify-between items-baseline">
          <h3 class="font-semibold">
            {{ str(exp.content, 'role') }}
            <span v-if="str(exp.content, 'company')" class="font-normal text-slate-600">
              — {{ str(exp.content, 'company') }}
            </span>
          </h3>
          <span class="text-xs text-slate-500">{{ dateRange(exp.content) }}</span>
        </div>
        <p v-if="str(exp.content, 'description')" class="text-sm text-slate-700 mt-1" style="white-space: pre-wrap;">
          {{ str(exp.content, 'description') }}
        </p>
      </div>
    </section>

    <section v-if="sectionsByType(resume.sections, 'education').length" class="mb-5">
      <h2 class="text-xs uppercase tracking-widest font-bold text-slate-500 mb-2">Education</h2>
      <div
        v-for="edu in sectionsByType(resume.sections, 'education')"
        :key="`edu-${edu.order}`"
        class="mb-2"
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

    <section v-if="sectionsByType(resume.sections, 'skill').length" class="mb-5">
      <h2 class="text-xs uppercase tracking-widest font-bold text-slate-500 mb-2">Skills</h2>
      <ul class="text-sm text-slate-800 flex flex-wrap gap-x-3 gap-y-1">
        <li v-for="sk in sectionsByType(resume.sections, 'skill')" :key="`sk-${sk.order}`">
          {{ str(sk.content, 'name') }}
          <span v-if="str(sk.content, 'level')" class="text-slate-500">· {{ str(sk.content, 'level') }}</span>
        </li>
      </ul>
    </section>

    <section v-if="sectionsByType(resume.sections, 'language').length" class="mb-5">
      <h2 class="text-xs uppercase tracking-widest font-bold text-slate-500 mb-2">Languages</h2>
      <ul class="text-sm text-slate-800 flex flex-wrap gap-x-3 gap-y-1">
        <li v-for="lang in sectionsByType(resume.sections, 'language')" :key="`lang-${lang.order}`">
          {{ str(lang.content, 'name') }}
          <span v-if="str(lang.content, 'level')" class="text-slate-500">· {{ str(lang.content, 'level') }}</span>
        </li>
      </ul>
    </section>

    <section v-if="sectionsByType(resume.sections, 'project').length">
      <h2 class="text-xs uppercase tracking-widest font-bold text-slate-500 mb-2">Projects</h2>
      <div
        v-for="proj in sectionsByType(resume.sections, 'project')"
        :key="`proj-${proj.order}`"
        class="mb-2"
      >
        <h3 class="font-semibold">
          {{ str(proj.content, 'name') }}
          <span v-if="str(proj.content, 'url')" class="font-normal text-slate-500 text-xs">
            · {{ str(proj.content, 'url') }}
          </span>
        </h3>
        <p v-if="str(proj.content, 'description')" class="text-sm text-slate-700" style="white-space: pre-wrap;">
          {{ str(proj.content, 'description') }}
        </p>
      </div>
    </section>
  </article>
</template>
