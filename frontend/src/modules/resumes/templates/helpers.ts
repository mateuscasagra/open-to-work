import type { ResumeSection, ResumeSectionType } from '@/shared/api/schemas';

export function sectionsByType(
  sections: ResumeSection[],
  type: ResumeSectionType
): ResumeSection[] {
  return sections.filter((s) => s.type === type).sort((a, b) => a.order - b.order);
}

export interface OrderedGroup {
  type: ResumeSectionType;
  sections: ResumeSection[];
}

export function orderedGroups(
  sections: ResumeSection[],
  only?: ResumeSectionType[]
): OrderedGroup[] {
  const sorted = [...sections].sort((a, b) => a.order - b.order);
  const groups: OrderedGroup[] = [];
  const idx = new Map<ResumeSectionType, number>();
  for (const s of sorted) {
    if (only && !only.includes(s.type)) continue;
    if (!idx.has(s.type)) {
      idx.set(s.type, groups.length);
      groups.push({ type: s.type, sections: [] });
    }
    groups[idx.get(s.type)!].sections.push(s);
  }
  return groups;
}

export function str(content: Record<string, unknown>, key: string): string {
  const v = content[key];
  return typeof v === 'string' ? v : '';
}

export function bool(content: Record<string, unknown>, key: string): boolean {
  return content[key] === true;
}

const CURRENT_LABELS: Record<string, string> = {
  pt: 'Atual',
  en: 'Present',
  es: 'Actual',
};

export function dateRange(content: Record<string, unknown>): string {
  const start = str(content, 'startDate');
  const lang = navigator.language.slice(0, 2);
  const end = bool(content, 'current')
    ? (CURRENT_LABELS[lang] ?? CURRENT_LABELS.en)
    : str(content, 'endDate');
  if (!start && !end) return '';
  return [start, end].filter(Boolean).join(' → ');
}

const LEVEL_LABELS: Record<string, Record<string, string>> = {
  pt: {
    beginner: 'Iniciante', intermediate: 'Intermediário', advanced: 'Avançado', expert: 'Especialista',
    basic: 'Básico', conversational: 'Conversação', fluent: 'Fluente', native: 'Nativo',
  },
  en: {
    beginner: 'Beginner', intermediate: 'Intermediate', advanced: 'Advanced', expert: 'Expert',
    basic: 'Basic', conversational: 'Conversational', fluent: 'Fluent', native: 'Native',
  },
  es: {
    beginner: 'Principiante', intermediate: 'Intermedio', advanced: 'Avanzado', expert: 'Experto',
    basic: 'Básico', conversational: 'Conversacional', fluent: 'Fluido', native: 'Nativo',
  },
};

export function levelLabel(level: string): string {
  const lang = navigator.language.slice(0, 2);
  const labels = LEVEL_LABELS[lang] ?? LEVEL_LABELS.en;
  return labels[level] ?? level;
}

const SECTION_LABELS: Record<string, Record<ResumeSectionType, string>> = {
  pt: {
    summary: 'Resumo',
    experience: 'Experiência',
    education: 'Formação',
    skill: 'Habilidades',
    language: 'Idiomas',
    project: 'Projetos',
    contact: 'Contato',
  },
  en: {
    summary: 'Summary',
    experience: 'Experience',
    education: 'Education',
    skill: 'Skills',
    language: 'Languages',
    project: 'Projects',
    contact: 'Contact',
  },
  es: {
    summary: 'Resumen',
    experience: 'Experiencia',
    education: 'Formación',
    skill: 'Habilidades',
    language: 'Idiomas',
    project: 'Proyectos',
    contact: 'Contacto',
  },
};

export function sectionLabel(type: ResumeSectionType): string {
  const lang = navigator.language.slice(0, 2);
  const labels = SECTION_LABELS[lang] ?? SECTION_LABELS.en;
  return labels[type];
}
