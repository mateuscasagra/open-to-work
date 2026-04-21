import { computed, type Ref } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { api } from '@/shared/api/client';
import { ResumeSchema } from '@/shared/api/schemas';

export function useResumeDetail(resumeId: Ref<number>) {
  const queryKey = computed(() => ['resume', resumeId.value] as const);

  return useQuery({
    queryKey,
    queryFn: async () => {
      const { data } = await api.get(`/api/resumes/${resumeId.value}`);
      return ResumeSchema.parse(data);
    },
    enabled: computed(() => Number.isFinite(resumeId.value) && resumeId.value > 0),
  });
}
