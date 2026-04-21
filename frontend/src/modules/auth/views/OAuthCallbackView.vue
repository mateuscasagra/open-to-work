<script setup lang="ts">
import { onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

onMounted(async () => {
  if (route.query.success === '1') {
    await auth.fetchMe();
    router.replace({ name: auth.user ? 'dashboard' : 'login' });
  } else {
    router.replace({ name: 'login' });
  }
});
</script>

<template>
  <div class="grid min-h-screen place-items-center bg-gradient-hero text-white">
    <div class="flex flex-col items-center gap-3">
      <span
        class="inline-block h-8 w-8 animate-spin rounded-full border-2 border-white/30 border-t-brand-300"
        aria-hidden="true"
      />
      <p class="text-sm text-white/70">Autenticando…</p>
    </div>
  </div>
</template>
