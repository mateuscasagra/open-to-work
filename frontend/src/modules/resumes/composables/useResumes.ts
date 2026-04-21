import { useQuery } from '@tanstack/vue-query';
import { api } from '@/shared/api/client';
import { ResumesPageSchema } from '@/shared/api/schemas';

export function useResumes() {
  return useQuery({
    queryKey: ['resumes'] as const,
    queryFn: async () => {
      const { data } = await api.get('/api/resumes');
      return ResumesPageSchema.parse(data);
    },
  });
}
