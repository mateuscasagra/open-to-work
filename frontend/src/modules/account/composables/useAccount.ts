import { ref } from 'vue';
import { api } from '@/shared/api/client';

export function useAccount() {
  const exporting = ref(false);
  const deleting = ref(false);
  const error = ref<string | null>(null);

  async function exportData(): Promise<void> {
    exporting.value = true;
    error.value = null;
    try {
      const response = await api.get('/api/account/export', { responseType: 'blob' });
      const blob = new Blob([response.data], { type: 'application/json' });
      const url = URL.createObjectURL(blob);
      const anchor = document.createElement('a');
      anchor.href = url;
      anchor.download = `opentowork-export-${Date.now()}.json`;
      document.body.appendChild(anchor);
      anchor.click();
      anchor.remove();
      URL.revokeObjectURL(url);
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Falha ao exportar dados';
      throw err;
    } finally {
      exporting.value = false;
    }
  }

  async function deleteAccount(): Promise<void> {
    deleting.value = true;
    error.value = null;
    try {
      await api.delete('/api/account');
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Falha ao excluir conta';
      throw err;
    } finally {
      deleting.value = false;
    }
  }

  return { exporting, deleting, error, exportData, deleteAccount };
}
