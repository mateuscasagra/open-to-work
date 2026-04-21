import { createI18n } from 'vue-i18n';
import ptBR from './pt-BR.json';
import en from './en.json';
import es from './es.json';

export type SupportedLocale = 'pt-BR' | 'en' | 'es';

export const i18n = createI18n({
  legacy: false,
  locale: (navigator.language.startsWith('pt')
    ? 'pt-BR'
    : navigator.language.startsWith('es')
      ? 'es'
      : 'en') as SupportedLocale,
  fallbackLocale: 'en',
  messages: {
    'pt-BR': ptBR,
    en,
    es,
  },
});
