import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { api } from '@/shared/api/client';
import {
  ResumeSchema,
  type Resume,
  type ResumeSection,
  type Locale,
} from '@/shared/api/schemas';

export interface SaveResumePayload {
  id?: number;
  title: string;
  language: Locale;
  sections: ResumeSection[];
}

export function useSaveResume() {
  const qc = useQueryClient();

  return useMutation<Resume, Error, SaveResumePayload>({
    mutationFn: async ({ id, title, language, sections }) => {
      const body = {
        title,
        language,
        sections: sections.map(({ type, order, content }) => ({ type, order, content })),
      };
      const { data } = id
        ? await api.put(`/api/resumes/${id}`, body)
        : await api.post('/api/resumes', body);
      return ResumeSchema.parse(data);
    },
    onSuccess: (resume) => {
      qc.invalidateQueries({ queryKey: ['resumes'] });
      qc.setQueryData(['resume', resume.id], resume);
    },
  });
}
