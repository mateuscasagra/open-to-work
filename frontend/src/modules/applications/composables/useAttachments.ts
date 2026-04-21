import { computed, type Ref } from 'vue';
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import { z } from 'zod';
import { api } from '@/shared/api/client';

export const AttachmentSchema = z.object({
  id: z.number(),
  name: z.string(),
  file_name: z.string(),
  mime_type: z.string().nullable(),
  size: z.number(),
  url: z.string(),
  created_at: z.string().nullable(),
});
export type Attachment = z.infer<typeof AttachmentSchema>;

const ListSchema = z.object({ data: z.array(AttachmentSchema) });

export function useAttachments(applicationId: Ref<number>) {
  const qc = useQueryClient();
  const queryKey = computed(() => ['application-attachments', applicationId.value] as const);

  const list = useQuery({
    queryKey,
    queryFn: async () => {
      const { data } = await api.get(`/api/applications/${applicationId.value}/attachments`);
      return ListSchema.parse(data).data;
    },
  });

  const upload = useMutation<Attachment, Error, File>({
    mutationFn: async (file) => {
      const form = new FormData();
      form.append('file', file);
      const { data } = await api.post(
        `/api/applications/${applicationId.value}/attachments`,
        form,
        { headers: { 'Content-Type': 'multipart/form-data' } }
      );
      return AttachmentSchema.parse(data);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKey.value });
      qc.invalidateQueries({ queryKey: ['application', applicationId.value] });
    },
  });

  const remove = useMutation<void, Error, number>({
    mutationFn: async (mediaId) => {
      await api.delete(`/api/applications/${applicationId.value}/attachments/${mediaId}`);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKey.value });
    },
  });

  return { list, upload, remove };
}
