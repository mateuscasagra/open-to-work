<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/modules/auth/stores/auth';

const { t } = useI18n();
const auth = useAuthStore();

const isAuthed = computed(() => !!auth.user);

const features = [
  { key: 'aggregation', icon: '🛰️' },
  { key: 'matching', icon: '🎯' },
  { key: 'kanban', icon: '🗂️' },
  { key: 'resumes', icon: '📄' },
  { key: 'metrics', icon: '📊' },
  { key: 'privacy', icon: '🔒' },
];

const steps = [
  { key: 'step1', number: '01' },
  { key: 'step2', number: '02' },
  { key: 'step3', number: '03' },
];
</script>

<template>
  <div class="min-h-screen bg-white text-ink-900">
    <!-- Nav -->
    <header class="absolute inset-x-0 top-0 z-20">
      <nav class="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
        <RouterLink :to="{ name: 'landing' }" class="flex items-center gap-2 text-white">
          <span
            class="grid h-8 w-8 place-items-center rounded-lg bg-brand-500 text-white font-bold shadow-glow"
          >
            O
          </span>
          <span class="font-semibold tracking-tight">{{ t('app.name') }}</span>
        </RouterLink>

        <div class="hidden items-center gap-8 text-sm text-white/80 md:flex">
          <a href="#features" class="hover:text-white transition">{{ t('nav.features') }}</a>
          <a href="#how" class="hover:text-white transition">{{ t('nav.how_it_works') }}</a>
        </div>

        <div class="flex items-center gap-3">
          <template v-if="isAuthed">
            <RouterLink :to="{ name: 'dashboard' }" class="btn-primary">
              {{ t('nav.open_app') }}
            </RouterLink>
          </template>
          <template v-else>
            <RouterLink
              :to="{ name: 'login' }"
              class="hidden text-sm font-medium text-white/80 hover:text-white sm:inline"
            >
              {{ t('auth.signIn') }}
            </RouterLink>
            <RouterLink :to="{ name: 'register' }" class="btn-primary">
              {{ t('landing.cta_primary') }}
            </RouterLink>
          </template>
        </div>
      </nav>
    </header>

    <!-- Hero -->
    <section class="relative overflow-hidden bg-gradient-hero pb-28 pt-32 text-white md:pb-40 md:pt-40">
      <!-- Grid overlay -->
      <div class="absolute inset-0 bg-grid-slate opacity-[0.06]" aria-hidden="true"></div>
      <!-- Soft glow -->
      <div
        class="absolute left-1/2 top-10 h-96 w-[48rem] -translate-x-1/2 rounded-full bg-brand-500/20 blur-3xl"
        aria-hidden="true"
      ></div>

      <div class="relative mx-auto max-w-4xl px-6 text-center">
        <span
          class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1 text-xs font-medium text-white/80 backdrop-blur"
        >
          <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
          {{ t('landing.eyebrow') }}
        </span>

        <h1 class="mt-8 text-4xl font-bold leading-tight tracking-tight md:text-6xl">
          {{ t('landing.title_1') }}
          <span
            class="block bg-gradient-to-r from-brand-300 via-emerald-400 to-teal-300 bg-clip-text text-transparent"
          >
            {{ t('landing.title_2') }}
          </span>
        </h1>

        <p class="mx-auto mt-6 max-w-2xl text-lg text-white/70 md:text-xl">
          {{ t('landing.subtitle') }}
        </p>

        <div class="mt-10 flex flex-wrap items-center justify-center gap-3">
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
            class="btn !px-6 !py-3 !text-base border border-white/15 bg-white/5 text-white hover:bg-white/10"
          >
            {{ t('landing.cta_secondary') }}
          </RouterLink>
        </div>

        <!-- Stats -->
        <dl class="mt-16 grid grid-cols-3 gap-6 border-t border-white/10 pt-10 text-left md:gap-10">
          <div>
            <dt class="text-xs uppercase tracking-wider text-white/50">
              {{ t('landing.stats.sources') }}
            </dt>
            <dd class="mt-1 text-2xl font-semibold text-white md:text-3xl">
              {{ t('landing.stats.sources_value') }}
            </dd>
          </div>
          <div>
            <dt class="text-xs uppercase tracking-wider text-white/50">
              {{ t('landing.stats.free') }}
            </dt>
            <dd class="mt-1 text-2xl font-semibold text-white md:text-3xl">
              {{ t('landing.stats.free_value') }}
            </dd>
          </div>
          <div>
            <dt class="text-xs uppercase tracking-wider text-white/50">
              {{ t('landing.stats.open_source') }}
            </dt>
            <dd class="mt-1 text-2xl font-semibold text-white md:text-3xl">
              {{ t('landing.stats.open_source_value') }}
            </dd>
          </div>
        </dl>
      </div>
    </section>

    <!-- Features -->
    <section id="features" class="relative bg-ink-50 py-24 md:py-32">
      <div class="mx-auto max-w-6xl px-6">
        <div class="mx-auto max-w-2xl text-center">
          <p class="text-sm font-semibold uppercase tracking-wider text-brand-600">
            {{ t('nav.features') }}
          </p>
          <h2 class="mt-2 text-3xl font-bold tracking-tight md:text-4xl">
            {{ t('landing.features_title') }}
          </h2>
          <p class="mt-4 text-ink-600">{{ t('landing.features_subtitle') }}</p>
        </div>

        <div class="mt-16 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
          <article
            v-for="feature in features"
            :key="feature.key"
            class="group relative overflow-hidden rounded-2xl border border-ink-200 bg-white p-6 shadow-soft transition hover:-translate-y-1 hover:border-brand-200 hover:shadow-card"
          >
            <div
              class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 text-2xl text-brand-600 ring-1 ring-inset ring-brand-100"
            >
              {{ feature.icon }}
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

    <!-- How it works -->
    <section id="how" class="relative bg-white py-24 md:py-32">
      <div class="mx-auto max-w-6xl px-6">
        <div class="mx-auto max-w-2xl text-center">
          <p class="text-sm font-semibold uppercase tracking-wider text-brand-600">
            {{ t('nav.how_it_works') }}
          </p>
          <h2 class="mt-2 text-3xl font-bold tracking-tight md:text-4xl">
            {{ t('landing.how_title') }}
          </h2>
          <p class="mt-4 text-ink-600">{{ t('landing.how_subtitle') }}</p>
        </div>

        <ol class="mt-16 grid gap-6 md:grid-cols-3">
          <li
            v-for="step in steps"
            :key="step.key"
            class="relative rounded-2xl border border-ink-200 bg-gradient-to-b from-white to-ink-50/60 p-6"
          >
            <span
              class="font-display text-5xl font-extrabold leading-none tracking-tighter text-transparent [-webkit-text-stroke:1px_theme(colors.brand.300)]"
            >
              {{ step.number }}
            </span>
            <h3 class="mt-4 text-lg font-semibold text-ink-900">
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
      <div class="mx-auto max-w-5xl px-6">
        <div
          class="relative overflow-hidden rounded-3xl bg-gradient-hero px-8 py-14 text-center text-white shadow-card md:px-16 md:py-20"
        >
          <div class="absolute inset-0 bg-grid-slate opacity-[0.06]" aria-hidden="true"></div>
          <div class="relative">
            <h2 class="text-3xl font-bold tracking-tight md:text-4xl">
              {{ t('landing.cta_final_title') }}
            </h2>
            <p class="mx-auto mt-4 max-w-xl text-white/70">
              {{ t('landing.cta_final_subtitle') }}
            </p>
            <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
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
                class="btn !px-6 !py-3 !text-base border border-white/15 bg-white/5 text-white hover:bg-white/10"
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
        class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-6 text-sm text-ink-500 md:flex-row"
      >
        <div class="flex items-center gap-2">
          <span
            class="grid h-6 w-6 place-items-center rounded-md bg-brand-600 text-xs font-bold text-white"
          >
            O
          </span>
          <span class="text-ink-700">{{ t('app.name') }}</span>
          <span>— {{ t('landing.footer.tagline') }}</span>
        </div>
        <div>© 2026 · {{ t('landing.footer.rights') }}</div>
      </div>
    </footer>
  </div>
</template>
