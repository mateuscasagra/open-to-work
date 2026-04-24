<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/modules/auth/stores/auth';
import logoSrc from '@/assets/logo.png';

const { t } = useI18n();
const auth = useAuthStore();

const isAuthed = computed(() => !!auth.user);

const features = [
  {
    key: 'jobs',
    icon: 'M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A9.072 9.072 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418',
  },
  {
    key: 'kanban',
    icon: 'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z',
  },
  {
    key: 'metrics',
    icon: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z',
  },
  {
    key: 'resumes',
    icon: 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9zM8.25 13.5h7.5m-7.5 3h7.5',
  },
];

const steps = [
  { key: 'step1', number: '01' },
  { key: 'step2', number: '02' },
  { key: 'step3', number: '03' },
];

const kanbanColumns = [
  {
    title: 'applications.status.applied',
    color: 'sky',
    count: 8,
    cards: [
      { company: 'Stripe', role: 'Backend Engineer', tag: 'Remoto' },
      { company: 'Notion', role: 'Fullstack Pleno', tag: 'Híbrido' },
    ],
  },
  {
    title: 'applications.status.interview_hr',
    color: 'amber',
    count: 3,
    cards: [
      { company: 'Linear', role: 'Software Engineer', tag: 'Remoto' },
    ],
  },
  {
    title: 'applications.status.interview_tech',
    color: 'violet',
    count: 2,
    cards: [
      { company: 'Vercel', role: 'Senior Frontend', tag: 'Remoto' },
    ],
  },
  {
    title: 'applications.status.offer',
    color: 'emerald',
    count: 1,
    cards: [
      { company: 'Figma', role: 'Tech Lead', tag: 'Híbrido' },
    ],
  },
];

const metricsBars = [
  { label: 'Seg', value: 38 },
  { label: 'Ter', value: 62 },
  { label: 'Qua', value: 45 },
  { label: 'Qui', value: 88 },
  { label: 'Sex', value: 72 },
  { label: 'Sáb', value: 22 },
  { label: 'Dom', value: 14 },
];
</script>

<template>
  <div class="min-h-screen bg-white text-ink-900">
    <!-- Nav -->
    <header class="absolute inset-x-0 top-0 z-20">
      <nav class="mx-auto flex max-w-7xl items-center justify-between px-6 py-5">
        <RouterLink
          :to="{ name: 'landing' }"
          class="flex items-center gap-2 transition hover:opacity-80"
        >
          <img :src="logoSrc" alt="Open to Work" class="h-20 object-contain">
        </RouterLink>

        <div class="hidden items-center gap-8 text-sm font-medium text-white/75 md:flex">
          <a
            href="#features"
            class="transition hover:text-white"
          >{{ t('nav.features') }}</a>
          <a
            href="#how"
            class="transition hover:text-white"
          >{{ t('nav.how_it_works') }}</a>
          <a
            href="#pricing"
            class="transition hover:text-white"
          >{{ t('landing.nav_pricing') }}</a>
        </div>

        <div class="flex items-center gap-3">
          <template v-if="isAuthed">
            <RouterLink
              :to="{ name: 'dashboard' }"
              class="btn-primary"
            >
              {{ t('nav.open_app') }}
            </RouterLink>
          </template>
          <template v-else>
            <RouterLink
              :to="{ name: 'login' }"
              class="hidden text-sm font-medium text-white/80 transition hover:text-white sm:inline"
            >
              {{ t('auth.signIn') }}
            </RouterLink>
            <RouterLink
              :to="{ name: 'register' }"
              class="btn-primary"
            >
              {{ t('landing.cta_primary') }}
            </RouterLink>
          </template>
        </div>
      </nav>
    </header>

    <!-- Hero -->
    <section class="relative overflow-hidden bg-gradient-hero pb-32 pt-32 text-white md:pb-48 md:pt-44">
      <!-- Grid overlay -->
      <div
        class="absolute inset-0 bg-grid-slate opacity-[0.06]"
        aria-hidden="true"
      />
      <!-- Soft glows -->
      <div
        class="absolute -left-32 top-20 h-96 w-96 rounded-full bg-brand-500/20 blur-3xl"
        aria-hidden="true"
      />
      <div
        class="absolute -right-32 top-40 h-[28rem] w-[28rem] rounded-full bg-emerald-400/10 blur-3xl"
        aria-hidden="true"
      />

      <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-6 lg:grid-cols-12 lg:gap-8">
        <div class="lg:col-span-6">
          <span
            class="inline-flex items-center gap-2 rounded-full border border-emerald-300/30 bg-emerald-400/10 px-3 py-1 text-xs font-medium text-emerald-200 backdrop-blur"
          >
            <span class="relative flex h-1.5 w-1.5">
              <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
              <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-400" />
            </span>
            {{ t('landing.eyebrow') }}
          </span>

          <h1 class="mt-6 text-4xl font-bold leading-[1.05] tracking-tight md:text-6xl">
            {{ t('landing.title_1') }}
            <span
              class="mt-1 block bg-gradient-to-r from-brand-300 via-emerald-400 to-teal-300 bg-clip-text text-transparent"
            >
              {{ t('landing.title_2') }}
            </span>
          </h1>

          <p class="mt-6 max-w-xl text-lg leading-relaxed text-white/70 md:text-xl">
            {{ t('landing.subtitle') }}
          </p>

          <div class="mt-10 flex flex-wrap items-center gap-3">
            <RouterLink
              :to="isAuthed ? { name: 'dashboard' } : { name: 'register' }"
              class="btn-primary !px-6 !py-3 !text-base shadow-glow"
            >
              {{ isAuthed ? t('nav.open_app') : t('landing.cta_primary') }}
              <span aria-hidden="true">→</span>
            </RouterLink>
            <RouterLink
              v-if="!isAuthed"
              :to="{ name: 'login' }"
              class="btn !px-6 !py-3 !text-base border border-white/15 bg-white/5 text-white backdrop-blur hover:bg-white/10"
            >
              {{ t('landing.cta_secondary') }}
            </RouterLink>
          </div>

          <!-- Trust strip -->
          <ul class="mt-10 flex flex-wrap items-center gap-x-6 gap-y-3 text-sm text-white/60">
            <li class="flex items-center gap-2">
              <svg class="h-4 w-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.5 12.75l6 6 9-13.5" />
              </svg>
              {{ t('landing.trust.no_card') }}
            </li>
            <li class="flex items-center gap-2">
              <svg class="h-4 w-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.5 12.75l6 6 9-13.5" />
              </svg>
              {{ t('landing.trust.no_ads') }}
            </li>
            <li class="flex items-center gap-2">
              <svg class="h-4 w-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.5 12.75l6 6 9-13.5" />
              </svg>
              {{ t('landing.trust.global') }}
            </li>
          </ul>
        </div>

        <!-- Hero mockup: Kanban preview -->
        <div class="relative lg:col-span-6">
          <div
            class="absolute inset-x-4 -bottom-6 -top-6 rounded-3xl bg-gradient-to-br from-brand-500/30 to-emerald-300/10 blur-2xl"
            aria-hidden="true"
          />
          <div class="relative rounded-2xl border border-white/10 bg-ink-900/60 p-3 shadow-2xl backdrop-blur-xl ring-1 ring-white/5">
            <!-- Window chrome -->
            <div class="flex items-center gap-1.5 border-b border-white/5 px-3 pb-3">
              <span class="h-2.5 w-2.5 rounded-full bg-red-400/70" />
              <span class="h-2.5 w-2.5 rounded-full bg-amber-400/70" />
              <span class="h-2.5 w-2.5 rounded-full bg-emerald-400/70" />
              <span class="ml-3 text-[10px] font-medium uppercase tracking-wider text-white/40">
                {{ t('nav.applications') }} — Kanban
              </span>
            </div>
            <!-- Kanban grid -->
            <div class="mt-3 grid grid-cols-4 gap-2 p-2">
              <div
                v-for="col in kanbanColumns"
                :key="col.title"
                class="rounded-xl bg-white/5 p-2 ring-1 ring-white/5"
              >
                <div class="flex items-center justify-between px-1 pb-2">
                  <div class="flex items-center gap-1.5">
                    <span
                      class="h-1.5 w-1.5 rounded-full"
                      :class="{
                        'bg-sky-400': col.color === 'sky',
                        'bg-amber-400': col.color === 'amber',
                        'bg-violet-400': col.color === 'violet',
                        'bg-emerald-400': col.color === 'emerald',
                      }"
                    />
                    <span class="text-[10px] font-semibold text-white/80 truncate">
                      {{ t(col.title) }}
                    </span>
                  </div>
                  <span class="rounded-full bg-white/10 px-1.5 text-[9px] font-bold text-white/70">
                    {{ col.count }}
                  </span>
                </div>
                <div class="space-y-1.5">
                  <div
                    v-for="(card, idx) in col.cards"
                    :key="idx"
                    class="rounded-lg bg-ink-800/80 p-2 ring-1 ring-white/5 transition hover:ring-brand-400/30"
                  >
                    <div class="text-[10px] font-semibold text-white">
                      {{ card.company }}
                    </div>
                    <div class="mt-0.5 text-[9px] text-white/60">
                      {{ card.role }}
                    </div>
                    <div class="mt-1.5 inline-flex items-center rounded-full bg-brand-500/15 px-1.5 py-0.5 text-[8px] font-medium text-brand-300 ring-1 ring-brand-400/20">
                      {{ card.tag }}
                    </div>
                  </div>
                  <div
                    v-if="col.cards.length === 0"
                    class="h-12 rounded-lg border border-dashed border-white/10"
                  />
                </div>
              </div>
            </div>
          </div>

          <!-- Floating mini metric card -->
          <div
            class="absolute -bottom-6 -left-4 hidden rounded-xl border border-white/10 bg-ink-900/85 p-3 shadow-2xl backdrop-blur ring-1 ring-white/5 md:block"
          >
            <div class="flex items-center gap-2">
              <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-500/20 text-emerald-400">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 18L9 11.25l4.306 4.306a11.95 11.95 0 015.814-5.518l2.74-1.22m0 0l-5.94-2.281m5.94 2.28l-2.28 5.941" />
                </svg>
              </div>
              <div>
                <div class="text-[9px] uppercase tracking-wider text-white/50">
                  {{ t('landing.hero.response_rate') }}
                </div>
                <div class="text-base font-bold text-white">
                  +24%
                </div>
              </div>
            </div>
          </div>

          <!-- Floating mini stage card -->
          <div
            class="absolute -right-3 -top-4 hidden rounded-xl border border-white/10 bg-ink-900/85 p-3 shadow-2xl backdrop-blur ring-1 ring-white/5 md:block"
          >
            <div class="flex items-center gap-2">
              <span class="relative flex h-2 w-2">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-brand-400 opacity-75" />
                <span class="relative inline-flex h-2 w-2 rounded-full bg-brand-400" />
              </span>
              <div class="text-[10px] font-semibold text-white">
                {{ t('landing.hero.stage_advanced') }}
              </div>
            </div>
            <div class="mt-1 text-[9px] text-white/60">
              Linear → {{ t('applications.status.interview_tech') }}
            </div>
          </div>
        </div>
      </div>

      <!-- Stats -->
      <div class="relative mx-auto mt-24 max-w-5xl px-6">
        <dl class="grid grid-cols-2 gap-6 border-t border-white/10 pt-10 md:grid-cols-4 md:gap-10">
          <div>
            <dt class="text-xs uppercase tracking-wider text-white/50">
              {{ t('landing.stats.coverage') }}
            </dt>
            <dd class="mt-1 text-2xl font-bold text-white md:text-3xl">
              {{ t('landing.stats.coverage_value') }}
            </dd>
          </div>
          <div>
            <dt class="text-xs uppercase tracking-wider text-white/50">
              {{ t('landing.stats.price') }}
            </dt>
            <dd class="mt-1 bg-gradient-to-r from-brand-300 to-emerald-400 bg-clip-text text-2xl font-bold text-transparent md:text-3xl">
              {{ t('landing.stats.price_value') }}
            </dd>
          </div>
          <div>
            <dt class="text-xs uppercase tracking-wider text-white/50">
              {{ t('landing.stats.stages') }}
            </dt>
            <dd class="mt-1 text-2xl font-bold text-white md:text-3xl">
              {{ t('landing.stats.stages_value') }}
            </dd>
          </div>
          <div>
            <dt class="text-xs uppercase tracking-wider text-white/50">
              {{ t('landing.stats.template') }}
            </dt>
            <dd class="mt-1 text-2xl font-bold text-white md:text-3xl">
              {{ t('landing.stats.template_value') }}
            </dd>
          </div>
        </dl>
      </div>
    </section>

    <!-- Features grid -->
    <section
      id="features"
      class="relative bg-ink-50 py-24 md:py-32"
    >
      <div class="mx-auto max-w-7xl px-6">
        <div class="mx-auto max-w-2xl text-center">
          <p class="text-sm font-semibold uppercase tracking-wider text-brand-600">
            {{ t('nav.features') }}
          </p>
          <h2 class="mt-3 text-3xl font-bold tracking-tight text-ink-900 md:text-4xl">
            {{ t('landing.features_title') }}
          </h2>
          <p class="mt-4 text-lg text-ink-600">
            {{ t('landing.features_subtitle') }}
          </p>
        </div>

        <div class="mt-16 grid gap-5 md:grid-cols-2 lg:grid-cols-4">
          <article
            v-for="feature in features"
            :key="feature.key"
            class="group relative overflow-hidden rounded-2xl border border-ink-200 bg-white p-6 shadow-soft transition-all duration-300 hover:-translate-y-1 hover:border-brand-200 hover:shadow-card"
          >
            <div
              class="absolute inset-x-0 -top-px h-px bg-gradient-to-r from-transparent via-brand-400/40 to-transparent opacity-0 transition-opacity group-hover:opacity-100"
              aria-hidden="true"
            />
            <div
              class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-brand-50 to-brand-100 text-brand-600 ring-1 ring-inset ring-brand-200/60"
            >
              <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="feature.icon" />
              </svg>
            </div>
            <h3 class="mt-5 text-lg font-semibold text-ink-900">
              {{ t(`landing.features.${feature.key}_title`) }}
            </h3>
            <p class="mt-2 text-sm leading-relaxed text-ink-600">
              {{ t(`landing.features.${feature.key}_desc`) }}
            </p>
          </article>
        </div>
      </div>
    </section>

    <!-- Spotlight 1 — Vagas globais -->
    <section class="relative bg-white py-24 md:py-32">
      <div class="mx-auto max-w-7xl px-6">
        <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
          <div>
            <span class="chip-brand">
              <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A9.072 9.072 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247" />
              </svg>
              {{ t('landing.spotlight.jobs_eyebrow') }}
            </span>
            <h2 class="mt-4 text-3xl font-bold tracking-tight text-ink-900 md:text-4xl">
              {{ t('landing.spotlight.jobs_title') }}
            </h2>
            <p class="mt-4 text-lg leading-relaxed text-ink-600">
              {{ t('landing.spotlight.jobs_desc') }}
            </p>
            <ul class="mt-6 space-y-3 text-sm text-ink-700">
              <li class="flex items-start gap-3">
                <span class="mt-0.5 flex h-5 w-5 flex-none items-center justify-center rounded-full bg-brand-100 text-brand-700">
                  <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M4.5 12.75l6 6 9-13.5" />
                  </svg>
                </span>
                {{ t('landing.spotlight.jobs_b1') }}
              </li>
              <li class="flex items-start gap-3">
                <span class="mt-0.5 flex h-5 w-5 flex-none items-center justify-center rounded-full bg-brand-100 text-brand-700">
                  <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M4.5 12.75l6 6 9-13.5" />
                  </svg>
                </span>
                {{ t('landing.spotlight.jobs_b2') }}
              </li>
              <li class="flex items-start gap-3">
                <span class="mt-0.5 flex h-5 w-5 flex-none items-center justify-center rounded-full bg-brand-100 text-brand-700">
                  <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M4.5 12.75l6 6 9-13.5" />
                  </svg>
                </span>
                {{ t('landing.spotlight.jobs_b3') }}
              </li>
            </ul>
          </div>

          <!-- Mockup: world + tags -->
          <div class="relative">
            <div class="relative overflow-hidden rounded-2xl border border-ink-200 bg-gradient-to-br from-ink-50 to-white p-6 shadow-card md:p-8">
              <!-- Globe-ish circle -->
              <div class="relative mx-auto aspect-square max-w-md">
                <div class="absolute inset-0 rounded-full bg-gradient-to-br from-brand-100 via-emerald-50 to-white" />
                <div class="absolute inset-4 rounded-full border border-brand-200/60" />
                <div class="absolute inset-12 rounded-full border border-brand-200/40" />
                <div class="absolute inset-20 rounded-full border border-brand-200/30" />

                <!-- Pulsing dots -->
                <span class="absolute left-[22%] top-[28%] h-2.5 w-2.5 rounded-full bg-brand-500 ring-4 ring-brand-500/20" />
                <span class="absolute left-[68%] top-[18%] h-2.5 w-2.5 rounded-full bg-emerald-500 ring-4 ring-emerald-500/20" />
                <span class="absolute left-[78%] top-[58%] h-2.5 w-2.5 rounded-full bg-brand-500 ring-4 ring-brand-500/20" />
                <span class="absolute left-[34%] top-[68%] h-2.5 w-2.5 rounded-full bg-emerald-500 ring-4 ring-emerald-500/20" />
                <span class="absolute left-[50%] top-[44%] h-3 w-3 rounded-full bg-brand-600 ring-4 ring-brand-600/30" />

                <!-- Floating job cards -->
                <div class="absolute -left-2 top-[12%] rounded-xl border border-ink-200 bg-white px-3 py-2 shadow-card">
                  <div class="text-[10px] font-medium text-ink-500">São Paulo · BR</div>
                  <div class="text-xs font-semibold text-ink-900">Backend Pleno</div>
                </div>
                <div class="absolute -right-2 top-[44%] rounded-xl border border-ink-200 bg-white px-3 py-2 shadow-card">
                  <div class="text-[10px] font-medium text-ink-500">Berlin · DE</div>
                  <div class="text-xs font-semibold text-ink-900">Senior Engineer</div>
                </div>
                <div class="absolute bottom-[8%] left-[18%] rounded-xl border border-ink-200 bg-white px-3 py-2 shadow-card">
                  <div class="text-[10px] font-medium text-ink-500">Remote · Worldwide</div>
                  <div class="text-xs font-semibold text-ink-900">Tech Lead</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Spotlight 2 — Métricas -->
    <section class="relative bg-ink-50 py-24 md:py-32">
      <div class="mx-auto max-w-7xl px-6">
        <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
          <!-- Mockup: chart -->
          <div class="order-2 lg:order-1">
            <div class="relative overflow-hidden rounded-2xl border border-ink-200 bg-white p-6 shadow-card md:p-8">
              <div class="flex items-center justify-between">
                <div>
                  <div class="text-xs font-medium uppercase tracking-wider text-ink-500">
                    {{ t('landing.spotlight.metrics_chart_label') }}
                  </div>
                  <div class="mt-1 flex items-baseline gap-2">
                    <span class="text-3xl font-bold text-ink-900">68%</span>
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">
                      <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
                      </svg>
                      +12%
                    </span>
                  </div>
                </div>
                <select disabled class="rounded-lg border border-ink-200 bg-ink-50 px-2 py-1 text-xs font-medium text-ink-600">
                  <option>{{ t('landing.spotlight.metrics_period') }}</option>
                </select>
              </div>

              <!-- Bars -->
              <div class="mt-8 flex items-end gap-3 px-1">
                <div
                  v-for="(bar, idx) in metricsBars"
                  :key="idx"
                  class="flex flex-1 flex-col items-center gap-2"
                >
                  <div class="flex h-32 w-full items-end">
                    <div
                      class="w-full rounded-t-md bg-gradient-to-t from-brand-500 to-brand-400 transition-all"
                      :style="{ height: bar.value + '%' }"
                    />
                  </div>
                  <span class="text-[10px] font-medium text-ink-500">{{ bar.label }}</span>
                </div>
              </div>

              <!-- Legend -->
              <div class="mt-6 grid grid-cols-3 gap-3 border-t border-ink-200 pt-4">
                <div>
                  <div class="text-[10px] uppercase tracking-wider text-ink-500">
                    {{ t('landing.spotlight.metrics_legend_applied') }}
                  </div>
                  <div class="text-base font-bold text-ink-900">42</div>
                </div>
                <div>
                  <div class="text-[10px] uppercase tracking-wider text-ink-500">
                    {{ t('landing.spotlight.metrics_legend_interview') }}
                  </div>
                  <div class="text-base font-bold text-ink-900">9</div>
                </div>
                <div>
                  <div class="text-[10px] uppercase tracking-wider text-ink-500">
                    {{ t('landing.spotlight.metrics_legend_offers') }}
                  </div>
                  <div class="text-base font-bold text-brand-600">3</div>
                </div>
              </div>
            </div>
          </div>

          <div class="order-1 lg:order-2">
            <span class="chip-brand">
              <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
              </svg>
              {{ t('landing.spotlight.metrics_eyebrow') }}
            </span>
            <h2 class="mt-4 text-3xl font-bold tracking-tight text-ink-900 md:text-4xl">
              {{ t('landing.spotlight.metrics_title') }}
            </h2>
            <p class="mt-4 text-lg leading-relaxed text-ink-600">
              {{ t('landing.spotlight.metrics_desc') }}
            </p>
            <ul class="mt-6 space-y-3 text-sm text-ink-700">
              <li class="flex items-start gap-3">
                <span class="mt-0.5 flex h-5 w-5 flex-none items-center justify-center rounded-full bg-brand-100 text-brand-700">
                  <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M4.5 12.75l6 6 9-13.5" />
                  </svg>
                </span>
                {{ t('landing.spotlight.metrics_b1') }}
              </li>
              <li class="flex items-start gap-3">
                <span class="mt-0.5 flex h-5 w-5 flex-none items-center justify-center rounded-full bg-brand-100 text-brand-700">
                  <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M4.5 12.75l6 6 9-13.5" />
                  </svg>
                </span>
                {{ t('landing.spotlight.metrics_b2') }}
              </li>
              <li class="flex items-start gap-3">
                <span class="mt-0.5 flex h-5 w-5 flex-none items-center justify-center rounded-full bg-brand-100 text-brand-700">
                  <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M4.5 12.75l6 6 9-13.5" />
                  </svg>
                </span>
                {{ t('landing.spotlight.metrics_b3') }}
              </li>
            </ul>
          </div>
        </div>
      </div>
    </section>

    <!-- Spotlight 3 — Currículo -->
    <section class="relative bg-white py-24 md:py-32">
      <div class="mx-auto max-w-7xl px-6">
        <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
          <div>
            <span class="chip-brand">
              <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
              </svg>
              {{ t('landing.spotlight.resumes_eyebrow') }}
            </span>
            <h2 class="mt-4 text-3xl font-bold tracking-tight text-ink-900 md:text-4xl">
              {{ t('landing.spotlight.resumes_title') }}
            </h2>
            <p class="mt-4 text-lg leading-relaxed text-ink-600">
              {{ t('landing.spotlight.resumes_desc') }}
            </p>
            <ul class="mt-6 space-y-3 text-sm text-ink-700">
              <li class="flex items-start gap-3">
                <span class="mt-0.5 flex h-5 w-5 flex-none items-center justify-center rounded-full bg-brand-100 text-brand-700">
                  <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M4.5 12.75l6 6 9-13.5" />
                  </svg>
                </span>
                {{ t('landing.spotlight.resumes_b1') }}
              </li>
              <li class="flex items-start gap-3">
                <span class="mt-0.5 flex h-5 w-5 flex-none items-center justify-center rounded-full bg-brand-100 text-brand-700">
                  <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M4.5 12.75l6 6 9-13.5" />
                  </svg>
                </span>
                {{ t('landing.spotlight.resumes_b2') }}
              </li>
              <li class="flex items-start gap-3">
                <span class="mt-0.5 flex h-5 w-5 flex-none items-center justify-center rounded-full bg-brand-100 text-brand-700">
                  <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M4.5 12.75l6 6 9-13.5" />
                  </svg>
                </span>
                {{ t('landing.spotlight.resumes_b3') }}
              </li>
            </ul>
          </div>

          <!-- Mockup: resume preview -->
          <div class="relative">
            <div
              class="absolute -inset-x-6 -inset-y-4 rounded-3xl bg-gradient-to-br from-brand-100/60 to-emerald-100/40 blur-2xl"
              aria-hidden="true"
            />
            <div class="relative mx-auto max-w-md rotate-[-2deg] overflow-hidden rounded-2xl border border-ink-200 bg-white shadow-card transition-transform hover:rotate-0">
              <!-- Resume header -->
              <div class="bg-gradient-to-br from-ink-900 to-brand-900 px-7 py-6 text-white">
                <div class="text-xl font-bold tracking-tight">Maria Silva</div>
                <div class="mt-1 text-sm text-white/70">Senior Software Engineer</div>
                <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-[10px] text-white/60">
                  <span>maria@email.com</span>
                  <span>+55 11 9 9999-0000</span>
                  <span>linkedin.com/in/maria</span>
                </div>
              </div>
              <!-- Resume body -->
              <div class="space-y-4 px-7 py-6">
                <div>
                  <div class="text-[10px] font-bold uppercase tracking-wider text-brand-600">
                    {{ t('resumes.sections.summary') }}
                  </div>
                  <div class="mt-1.5 space-y-1">
                    <div class="h-1.5 w-full rounded-full bg-ink-200" />
                    <div class="h-1.5 w-11/12 rounded-full bg-ink-200" />
                    <div class="h-1.5 w-9/12 rounded-full bg-ink-200" />
                  </div>
                </div>
                <div>
                  <div class="text-[10px] font-bold uppercase tracking-wider text-brand-600">
                    {{ t('resumes.sections.experience') }}
                  </div>
                  <div class="mt-2 space-y-2">
                    <div>
                      <div class="flex items-baseline justify-between">
                        <div class="text-xs font-semibold text-ink-900">Tech Lead · Acme</div>
                        <div class="text-[9px] text-ink-500">2022 — atual</div>
                      </div>
                      <div class="mt-1.5 space-y-1">
                        <div class="h-1.5 w-full rounded-full bg-ink-200" />
                        <div class="h-1.5 w-10/12 rounded-full bg-ink-200" />
                      </div>
                    </div>
                    <div>
                      <div class="flex items-baseline justify-between">
                        <div class="text-xs font-semibold text-ink-900">Sr. Engineer · Globex</div>
                        <div class="text-[9px] text-ink-500">2019 — 2022</div>
                      </div>
                      <div class="mt-1.5 space-y-1">
                        <div class="h-1.5 w-full rounded-full bg-ink-200" />
                        <div class="h-1.5 w-9/12 rounded-full bg-ink-200" />
                      </div>
                    </div>
                  </div>
                </div>
                <div>
                  <div class="text-[10px] font-bold uppercase tracking-wider text-brand-600">
                    {{ t('resumes.sections.skill') }}
                  </div>
                  <div class="mt-2 flex flex-wrap gap-1">
                    <span class="rounded-md bg-brand-50 px-2 py-0.5 text-[10px] font-semibold text-brand-700">Vue 3</span>
                    <span class="rounded-md bg-brand-50 px-2 py-0.5 text-[10px] font-semibold text-brand-700">Laravel</span>
                    <span class="rounded-md bg-brand-50 px-2 py-0.5 text-[10px] font-semibold text-brand-700">TypeScript</span>
                    <span class="rounded-md bg-brand-50 px-2 py-0.5 text-[10px] font-semibold text-brand-700">PostgreSQL</span>
                    <span class="rounded-md bg-brand-50 px-2 py-0.5 text-[10px] font-semibold text-brand-700">Docker</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- PDF badge -->
            <div class="absolute -right-2 -top-2 inline-flex items-center gap-1.5 rounded-full bg-brand-600 px-3 py-1.5 text-xs font-bold text-white shadow-glow">
              <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
              </svg>
              {{ t('landing.spotlight.resumes_pdf_badge') }}
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Pricing / Free banner -->
    <section
      id="pricing"
      class="relative overflow-hidden bg-ink-900 py-24 md:py-32"
    >
      <div
        class="absolute inset-0 bg-grid-slate opacity-[0.04]"
        aria-hidden="true"
      />
      <div
        class="absolute left-1/2 top-0 h-96 w-[60rem] -translate-x-1/2 rounded-full bg-brand-500/15 blur-3xl"
        aria-hidden="true"
      />
      <div class="relative mx-auto max-w-4xl px-6 text-center">
        <span
          class="inline-flex items-center gap-2 rounded-full border border-emerald-300/30 bg-emerald-400/10 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-emerald-300"
        >
          {{ t('landing.pricing.eyebrow') }}
        </span>
        <h2 class="mt-6 text-4xl font-bold leading-tight tracking-tight text-white md:text-5xl">
          {{ t('landing.pricing.title_1') }}
          <span class="bg-gradient-to-r from-brand-300 via-emerald-400 to-teal-300 bg-clip-text text-transparent">
            {{ t('landing.pricing.title_2') }}
          </span>
        </h2>
        <p class="mx-auto mt-6 max-w-2xl text-lg text-white/70">
          {{ t('landing.pricing.subtitle') }}
        </p>

        <div class="mt-10 inline-flex items-baseline gap-2 rounded-2xl border border-white/10 bg-white/5 px-8 py-6 backdrop-blur">
          <span class="text-6xl font-bold text-white md:text-7xl">{{ t('landing.pricing.price') }}</span>
          <span class="text-base text-white/60">/{{ t('landing.pricing.forever') }}</span>
        </div>

        <ul class="mx-auto mt-10 grid max-w-2xl gap-3 text-left sm:grid-cols-2">
          <li class="flex items-start gap-3 rounded-xl border border-white/10 bg-white/5 p-4 backdrop-blur">
            <span class="mt-0.5 flex h-5 w-5 flex-none items-center justify-center rounded-full bg-brand-500/20 text-brand-300">
              <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M4.5 12.75l6 6 9-13.5" />
              </svg>
            </span>
            <span class="text-sm text-white/80">{{ t('landing.pricing.b1') }}</span>
          </li>
          <li class="flex items-start gap-3 rounded-xl border border-white/10 bg-white/5 p-4 backdrop-blur">
            <span class="mt-0.5 flex h-5 w-5 flex-none items-center justify-center rounded-full bg-brand-500/20 text-brand-300">
              <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M4.5 12.75l6 6 9-13.5" />
              </svg>
            </span>
            <span class="text-sm text-white/80">{{ t('landing.pricing.b2') }}</span>
          </li>
          <li class="flex items-start gap-3 rounded-xl border border-white/10 bg-white/5 p-4 backdrop-blur">
            <span class="mt-0.5 flex h-5 w-5 flex-none items-center justify-center rounded-full bg-brand-500/20 text-brand-300">
              <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M4.5 12.75l6 6 9-13.5" />
              </svg>
            </span>
            <span class="text-sm text-white/80">{{ t('landing.pricing.b3') }}</span>
          </li>
          <li class="flex items-start gap-3 rounded-xl border border-white/10 bg-white/5 p-4 backdrop-blur">
            <span class="mt-0.5 flex h-5 w-5 flex-none items-center justify-center rounded-full bg-brand-500/20 text-brand-300">
              <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M4.5 12.75l6 6 9-13.5" />
              </svg>
            </span>
            <span class="text-sm text-white/80">{{ t('landing.pricing.b4') }}</span>
          </li>
        </ul>
      </div>
    </section>

    <!-- How it works -->
    <section
      id="how"
      class="relative bg-white py-24 md:py-32"
    >
      <div class="mx-auto max-w-7xl px-6">
        <div class="mx-auto max-w-2xl text-center">
          <p class="text-sm font-semibold uppercase tracking-wider text-brand-600">
            {{ t('nav.how_it_works') }}
          </p>
          <h2 class="mt-3 text-3xl font-bold tracking-tight text-ink-900 md:text-4xl">
            {{ t('landing.how_title') }}
          </h2>
          <p class="mt-4 text-lg text-ink-600">
            {{ t('landing.how_subtitle') }}
          </p>
        </div>

        <ol class="relative mt-16 grid gap-6 md:grid-cols-3">
          <!-- Connecting line -->
          <div
            class="absolute left-0 right-0 top-12 hidden h-px bg-gradient-to-r from-transparent via-brand-300 to-transparent md:block"
            aria-hidden="true"
          />
          <li
            v-for="step in steps"
            :key="step.key"
            class="relative rounded-2xl border border-ink-200 bg-gradient-to-b from-white to-ink-50/60 p-6 shadow-soft"
          >
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-600 text-base font-bold text-white shadow-glow">
              {{ step.number }}
            </div>
            <h3 class="mt-5 text-lg font-semibold text-ink-900">
              {{ t(`landing.how.${step.key}_title`) }}
            </h3>
            <p class="mt-2 text-sm leading-relaxed text-ink-600">
              {{ t(`landing.how.${step.key}_desc`) }}
            </p>
          </li>
        </ol>
      </div>
    </section>

    <!-- Final CTA -->
    <section class="bg-ink-50 pb-24">
      <div class="mx-auto max-w-6xl px-6">
        <div
          class="relative overflow-hidden rounded-3xl bg-gradient-hero px-8 py-16 text-center text-white shadow-card md:px-16 md:py-24"
        >
          <div
            class="absolute inset-0 bg-grid-slate opacity-[0.06]"
            aria-hidden="true"
          />
          <div
            class="absolute left-1/2 top-0 h-72 w-[40rem] -translate-x-1/2 rounded-full bg-brand-500/20 blur-3xl"
            aria-hidden="true"
          />
          <div class="relative">
            <h2 class="text-3xl font-bold leading-tight tracking-tight md:text-5xl">
              {{ t('landing.cta_final_title') }}
            </h2>
            <p class="mx-auto mt-5 max-w-2xl text-lg text-white/70">
              {{ t('landing.cta_final_subtitle') }}
            </p>
            <div class="mt-10 flex flex-wrap items-center justify-center gap-3">
              <RouterLink
                :to="isAuthed ? { name: 'dashboard' } : { name: 'register' }"
                class="btn-primary !px-7 !py-3.5 !text-base shadow-glow"
              >
                {{ isAuthed ? t('nav.open_app') : t('landing.cta_primary') }}
                <span aria-hidden="true">→</span>
              </RouterLink>
              <RouterLink
                v-if="!isAuthed"
                :to="{ name: 'login' }"
                class="btn !px-7 !py-3.5 !text-base border border-white/15 bg-white/5 text-white backdrop-blur hover:bg-white/10"
              >
                {{ t('auth.signIn') }}
              </RouterLink>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="border-t border-ink-200 bg-white py-10">
      <div
        class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-6 text-sm text-ink-500 md:flex-row"
      >
        <div class="flex items-center gap-3">
          <img :src="logoSrc" alt="Open to Work" class="h-20 object-contain">
          <span class="hidden md:inline">— {{ t('landing.footer.tagline') }}</span>
        </div>
        <div>© 2026 · {{ t('landing.footer.rights') }}</div>
      </div>
    </footer>
  </div>
</template>
