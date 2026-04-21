import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { api } from '@/shared/api/client';
import { ResumeSchema, type Resume, type Locale } from '@/shared/api/schemas';

export interface UploadResumePdfPayload {
  title: string;
  language?: Locale;
  file: File;
}

export function useUploadResumePdf() {
  const qc = useQueryClient();

  return useMutation<Resume, Error, UploadResumePdfPayload>({
    mutationFn: async ({ title, language, file }) => {
      const form = new FormData();
      form.append('title', title);
      if (language) form.append('language', language);
      form.append('file', file);

      const { data } = await api.post('/api/resumes/pdf', form, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      return ResumeSchema.parse(data);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['resumes'] });
    },
  });
}
