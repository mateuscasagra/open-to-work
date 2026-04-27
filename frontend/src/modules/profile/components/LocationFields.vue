<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocationLookup } from '../composables/useLocationLookup';
import type { SupportedCountry } from '@/shared/api/schemas';

interface LocationModel {
  country_code: string | null;
  postal_code: string | null;
  state_code: string | null;
  state_name: string;
  city: string;
}

const props = defineProps<{
  modelValue: LocationModel;
  countries: SupportedCountry[];
  fieldErrors?: Record<string, string[]>;
}>();

const emit = defineEmits<{
  (e: 'update:modelValue', value: LocationModel): void;
}>();

const { t, locale } = useI18n();
const { lookup, loading, errorKey, clearError } = useLocationLookup();

const DEBOUNCE_MS = 500;

const currentCountry = computed<SupportedCountry | null>(() => {
  if (!props.modelValue.country_code) return null;
  return props.countries.find((c) => c.code === props.modelValue.country_code) ?? null;
});

const countryName = (country: SupportedCountry): string => {
  if (locale.value === 'pt_BR') return country.name_pt;
  if (locale.value === 'es') return country.name_es;
  return country.name_en;
};

const postalRegex = computed<RegExp | null>(() => {
  const pattern = currentCountry.value?.postal_pattern;
  if (!pattern) return null;
  try {
    return new RegExp(pattern);
  } catch {
    return null;
  }
});

const postalMatchesPattern = computed<boolean>(() => {
  if (!postalRegex.value || !props.modelValue.postal_code) return false;
  return postalRegex.value.test(props.modelValue.postal_code);
});

const canLookup = computed<boolean>(() => {
  return Boolean(currentCountry.value?.supports_lookup) && postalMatchesPattern.value;
});

let debounceHandle: ReturnType<typeof setTimeout> | null = null;
const lastLookupKey = ref<string | null>(null);

function setField<K extends keyof LocationModel>(key: K, value: LocationModel[K]): void {
  emit('update:modelValue', { ...props.modelValue, [key]: value });
}

function setMany(patch: Partial<LocationModel>): void {
  emit('update:modelValue', { ...props.modelValue, ...patch });
}

async function runLookup(force = false): Promise<void> {
  const country = currentCountry.value;
  const postal = props.modelValue.postal_code ?? '';
  if (!country || !country.supports_lookup) return;
  if (!postalMatchesPattern.value) return;

  const key = `${country.code}:${postal}`;
  if (!force && key === lastLookupKey.value) return;
  lastLookupKey.value = key;

  try {
    const result = await lookup(country.code, postal);
    setMany({
      state_code: result.state_code,
      state_name: result.state_name ?? '',
      city: result.city ?? '',
    });
  } catch {
    // erro já capturado em errorKey pelo composable
  }
}

watch(
  () => [props.modelValue.country_code, props.modelValue.postal_code] as const,
  ([newCountry, newPostal], oldValues) => {
    const [oldCountry] = oldValues ?? [null, null];
    if (newCountry !== oldCountry) {
      lastLookupKey.value = null;
      clearError();
    }
    if (debounceHandle) {
      clearTimeout(debounceHandle);
      debounceHandle = null;
    }
    if (canLookup.value && newPostal) {
      debounceHandle = setTimeout(() => {
        void runLookup();
      }, DEBOUNCE_MS);
    }
  },
);

function onCountryChange(event: Event): void {
  const target = event.target as HTMLSelectElement;
  setMany({
    country_code: target.value || null,
    postal_code: null,
    state_code: null,
    state_name: '',
    city: '',
  });
  clearError();
}

function onManualLookup(): void {
  void runLookup(true);
}
</script>

<template>
  <fieldset class="rounded-lg border border-ink-200 bg-white p-4">
    <legend class="px-1 text-xs font-semibold uppercase tracking-wider text-ink-500">
      {{ t('profile.location_section') }}
    </legend>

    <label class="block">
      <span class="label">{{ t('profile.country') }} <span class="text-red-500">*</span></span>
      <select
        :value="modelValue.country_code ?? ''"
        class="input mt-1.5"
        @change="onCountryChange"
      >
        <option value="">—</option>
        <option
          v-for="c in countries"
          :key="c.code"
          :value="c.code"
        >
          {{ countryName(c) }}
        </option>
      </select>
      <span
        v-if="fieldErrors?.country_code"
        class="mt-1 block text-xs text-red-600"
      >
        {{ fieldErrors.country_code[0] }}
      </span>
    </label>

    <div
      v-if="modelValue.country_code"
      class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-end"
    >
      <label class="block flex-1">
        <span class="label">
          {{ t('profile.postal_code') }}
          <span
            v-if="currentCountry?.supports_lookup"
            class="text-red-500"
          >*</span>
        </span>
        <input
          :value="modelValue.postal_code ?? ''"
          type="text"
          autocomplete="postal-code"
          :placeholder="currentCountry?.postal_example"
          class="input mt-1.5"
          @input="(e) => setField('postal_code', (e.target as HTMLInputElement).value || null)"
        >
        <span
          v-if="fieldErrors?.postal_code"
          class="mt-1 block text-xs text-red-600"
        >
          {{ fieldErrors.postal_code[0] }}
        </span>
      </label>
      <button
        v-if="currentCountry?.supports_lookup"
        type="button"
        class="btn-secondary sm:flex-none"
        :disabled="loading || !canLookup"
        @click="onManualLookup"
      >
        {{ loading ? t('profile.location_searching') : t('profile.location_search') }}
      </button>
    </div>

    <p
      v-if="errorKey"
      class="mt-2 text-xs text-amber-600"
    >
      {{ t(errorKey) }}
    </p>

    <div
      v-if="modelValue.country_code"
      class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2"
    >
      <label class="block">
        <span class="label">{{ t('profile.state') }} <span class="text-red-500">*</span></span>
        <input
          :value="modelValue.state_name"
          type="text"
          autocomplete="address-level1"
          class="input mt-1.5"
          @input="(e) => setField('state_name', (e.target as HTMLInputElement).value)"
        >
        <span
          v-if="fieldErrors?.state_name"
          class="mt-1 block text-xs text-red-600"
        >
          {{ fieldErrors.state_name[0] }}
        </span>
      </label>
      <label class="block">
        <span class="label">{{ t('profile.city') }} <span class="text-red-500">*</span></span>
        <input
          :value="modelValue.city"
          type="text"
          autocomplete="address-level2"
          class="input mt-1.5"
          @input="(e) => setField('city', (e.target as HTMLInputElement).value)"
        >
        <span
          v-if="fieldErrors?.city"
          class="mt-1 block text-xs text-red-600"
        >
          {{ fieldErrors.city[0] }}
        </span>
      </label>
    </div>
  </fieldset>
</template>
