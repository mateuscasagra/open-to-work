import { api } from '@/shared/api/client';

export async function downloadResumePdf(id: number): Promise<string> {
  const { data } = await api.get<{ url: string }>(`/api/resumes/${id}/download`);
  return data.url;
}
