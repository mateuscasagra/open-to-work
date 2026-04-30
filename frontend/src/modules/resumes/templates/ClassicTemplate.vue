<script setup lang="ts">
import type { Resume } from '@/shared/api/schemas';
import { dateRange, orderedGroups, sectionsByType, str, sectionLabel, levelLabel } from './helpers';

defineProps<{ resume: Resume; userName?: string }>();

const BODY_TYPES = ['summary', 'experience', 'education', 'skill', 'language', 'project'] as const;
</script>

<template>
  <article
    class="pdf-page bg-white text-slate-900"
    style="width: 794px; min-height: 1123px; padding: 64px 72px; font-family: 'Calibri', 'Carlito', 'Arial', sans-serif;"
  >
    <header class="border-b border-slate-300 pb-4 mb-6">
      <h1 class="text-3xl font-bold tracking-tight">
        {{ userName ?? resume.title }}
      </h1>
      <p class="text-sm text-slate-600 mt-1">
        {{ resume.title }}
      </p>
      <div
        v-if="sectionsByType(resume.sections, 'contact').length"
        class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600"
      >
        <template
          v-for="ct in sectionsByType(resume.sections, 'contact')"
          :key="`ct-${ct.order}`"
        >
          <span v-if="str(ct.content, 'email')">{{ str(ct.content, 'email') }}</span>
          <span v-if="str(ct.content, 'phone')">{{ str(ct.content, 'phone') }}</span>
          <span v-if="str(ct.content, 'linkedin')">{{ str(ct.content, 'linkedin') }}</span>
          <span v-if="str(ct.content, 'github')">{{ str(ct.content, 'github') }}</span>
          <span v-if="str(ct.content, 'website')">{{ str(ct.content, 'website') }}</span>
          <span v-if="str(ct.content, 'address')">{{ str(ct.content, 'address') }}</span>
        </template>
      </div>
    </header>

    <template
      v-for="group in orderedGroups(resume.sections, [...BODY_TYPES])"
      :key="group.type"
    >
      <section
        v-if="group.type === 'summary'"
        class="mb-5"
      >
        <p
          v-for="sum in group.sections"
          :key="`sum-${sum.order}`"
          class="text-sm leading-relaxed text-slate-800"
          style="white-space: pre-wrap;"
        >
          {{ str(sum.content, 'text') }}
        </p>
      </section>

      <section
        v-else-if="group.type === 'experience'"
        class="mb-5"
      >
        <h2 class="text-xs uppercase tracking-widest font-bold text-slate-500 mb-2">
          {{ sectionLabel('experience') }}
        </h2>
        <div
          v-for="exp in group.sections"
          :key="`exp-${exp.order}`"
          class="mb-3"
        >
          <div class="flex justify-between items-baseline">
            <h3 class="font-semibold">
              {{ str(exp.content, 'role') }}
              <span
                v-if="str(exp.content, 'company')"
                class="font-normal text-slate-600"
              >
                — {{ str(exp.content, 'company') }}
              </span>
            </h3>
            <span class="text-xs text-slate-500">{{ dateRange(exp.content) }}</span>
          </div>
          <p
            v-if="str(exp.content, 'description')"
            class="text-sm text-slate-700 mt-1"
            style="white-space: pre-wrap;"
          >
            {{ str(exp.content, 'description') }}
          </p>
        </div>
      </section>

      <section
        v-else-if="group.type === 'education'"
        class="mb-5"
      >
        <h2 class="text-xs uppercase tracking-widest font-bold text-slate-500 mb-2">
          {{ sectionLabel('education') }}
        </h2>
        <div
          v-for="edu in group.sections"
          :key="`edu-${edu.order}`"
          class="mb-2"
        >
          <div class="flex justify-between items-baseline">
            <h3 class="font-semibold">
              {{ str(edu.content, 'degree') }}
            </h3>
            <span class="text-xs text-slate-500">{{ dateRange(edu.content) }}</span>
          </div>
          <p class="text-sm text-slate-700">
            {{ str(edu.content, 'institution') }}
          </p>
        </div>
      </section>

      <section
        v-else-if="group.type === 'skill'"
        class="mb-5"
      >
        <h2 class="text-xs uppercase tracking-widest font-bold text-slate-500 mb-2">
          {{ sectionLabel('skill') }}
        </h2>
        <ul class="text-sm text-slate-800 flex flex-wrap gap-x-3 gap-y-1">
          <li
            v-for="sk in group.sections"
            :key="`sk-${sk.order}`"
          >
            {{ str(sk.content, 'name') }}
            <span
              v-if="str(sk.content, 'level')"
              class="text-slate-500"
            >· {{ levelLabel(str(sk.content, 'level')) }}</span>
          </li>
        </ul>
      </section>

      <section
        v-else-if="group.type === 'language'"
        class="mb-5"
      >
        <h2 class="text-xs uppercase tracking-widest font-bold text-slate-500 mb-2">
          {{ sectionLabel('language') }}
        </h2>
        <ul class="text-sm text-slate-800 flex flex-wrap gap-x-3 gap-y-1">
          <li
            v-for="lang in group.sections"
            :key="`lang-${lang.order}`"
          >
            {{ str(lang.content, 'name') }}
            <span
              v-if="str(lang.content, 'level')"
              class="text-slate-500"
            >· {{ levelLabel(str(lang.content, 'level')) }}</span>
          </li>
        </ul>
      </section>

      <section v-else-if="group.type === 'project'">
        <h2 class="text-xs uppercase tracking-widest font-bold text-slate-500 mb-2">
          {{ sectionLabel('project') }}
        </h2>
        <div
          v-for="proj in group.sections"
          :key="`proj-${proj.order}`"
          class="mb-2"
        >
          <h3 class="font-semibold">
            {{ str(proj.content, 'name') }}
            <span
              v-if="str(proj.content, 'url')"
              class="font-normal text-slate-500 text-xs"
            >
              · {{ str(proj.content, 'url') }}
            </span>
          </h3>
          <p
            v-if="str(proj.content, 'description')"
            class="text-sm text-slate-700"
            style="white-space: pre-wrap;"
          >
            {{ str(proj.content, 'description') }}
          </p>
        </div>
      </section>
    </template>
  </article>
</template>
