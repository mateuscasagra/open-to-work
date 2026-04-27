import { ref } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { z } from 'zod';
import { api } from '@/shared/api/client';
import {
  PostalCodeLookupResultSchema,
  SupportedCountrySchema,
  type PostalCodeLookupResult,
  type SupportedCountry,
} from '@/shared/api/schemas';

const SupportedCountryListSchema = z.array(SupportedCountrySchema);

export function useSupportedCountries() {
  return useQuery({
    queryKey: ['location', 'countries'],
    queryFn: async (): Promise<SupportedCountry[]> => {
      const { data } = await api.get('/api/location/countries');
      return SupportedCountryListSchema.parse(data);
    },
    staleTime: Infinity,
    gcTime: Infinity,
  });
}

export type LocationLookupErrorKey =
  | 'profile.location_not_found'
  | 'profile.location_unsupported'
  | 'profile.location_invalid'
  | 'profile.location_api_failed';

export function useLocationLookup() {
  const loading = ref(false);
  const errorKey = ref<LocationLookupErrorKey | null>(null);

  async function lookup(
    country_code: string,
    postal_code: string,
  ): Promise<PostalCodeLookupResult> {
    loading.value = true;
    errorKey.value = null;
    try {
      const { data } = await api.post('/api/location/lookup', {
        country_code,
        postal_code,
      });
      return PostalCodeLookupResultSchema.parse(data);
    } catch (e: unknown) {
      const status = (e as { response?: { status?: number } })?.response?.status;
      if (status === 404) {
        errorKey.value = 'profile.location_not_found';
      } else if (status === 422) {
        errorKey.value = 'profile.location_invalid';
      } else if (status === 502) {
        errorKey.value = 'profile.location_api_failed';
      } else {
        errorKey.value = 'profile.location_api_failed';
      }
      throw e;
    } finally {
      loading.value = false;
    }
  }

  function clearError(): void {
    errorKey.value = null;
  }

  return { lookup, loading, errorKey, clearError };
}
