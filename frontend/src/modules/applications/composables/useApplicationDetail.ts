import { computed, type Ref } from 'vue';
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import { api } from '@/shared/api/client';
import { ApplicationSchema, type Application } from '@/shared/api/schemas';

export function useApplicationDetail(applicationId: Ref<number>) {
  const qc = useQueryClient();

  const queryKey = computed(() => ['application', applicationId.value] as const);

  const detail = useQuery({
    queryKey,
    queryFn: async () => {
      const { data } = await api.get(`/api/applications/${applicationId.value}`);
      return ApplicationSchema.parse(data);
    },
  });

  const updateNotes = useMutation<
    Application,
    Error,
    { notes?: string; expectedSalary?: number; resumeId?: number | null }
  >({
    mutationFn: async ({ notes, expectedSalary, resumeId }) => {
      const body: Record<string, unknown> = {};
      if (notes !== undefined) body.notes = notes;
      if (expectedSalary !== undefined) body.expected_salary = expectedSalary;
      if (resumeId !== undefined) body.resume_id = resumeId;

      const { data } = await api.put(`/api/applications/${applicationId.value}`, body);
      return ApplicationSchema.parse(data);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKey.value });
      qc.invalidateQueries({ queryKey: ['applications'] });
    },
  });

  return { detail, updateNotes };
}
