import { ref } from 'vue';
import { api } from '@/shared/api/client';
import { ProfileSchema, SkillSchema, type Profile, type Skill } from '@/shared/api/schemas';
import { z } from 'zod';

const SkillListSchema = z.object({ data: z.array(SkillSchema) });

export function useProfile() {
  const profile = ref<Profile | null>(null);
  const loading = ref(false);
  const error = ref<string | null>(null);

  async function fetch(): Promise<void> {
    loading.value = true;
    try {
      const { data } = await api.get('/api/profile');
      profile.value = ProfileSchema.parse(data);
    } finally {
      loading.value = false;
    }
  }

  async function save(patch: Omit<Partial<Profile>, 'skills'> & { skills?: number[] }): Promise<Profile> {
    loading.value = true;
    error.value = null;
    try {
      const { data } = await api.put('/api/profile', patch);
      profile.value = ProfileSchema.parse(data);
      return profile.value;
    } finally {
      loading.value = false;
    }
  }

  return { profile, loading, error, fetch, save };
}

export async function searchSkills(q: string): Promise<Skill[]> {
  const { data } = await api.get('/api/skills', { params: { q } });
  return SkillListSchema.parse(data).data;
}
