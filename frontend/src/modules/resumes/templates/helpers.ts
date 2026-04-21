import type { ResumeSection, ResumeSectionType } from '@/shared/api/schemas';

export function sectionsByType(
  sections: ResumeSection[],
  type: ResumeSectionType
): ResumeSection[] {
  return sections.filter((s) => s.type === type).sort((a, b) => a.order - b.order);
}

export function str(content: Record<string, unknown>, key: string): string {
  const v = content[key];
  return typeof v === 'string' ? v : '';
}

export function bool(content: Record<string, unknown>, key: string): boolean {
  return content[key] === true;
}

export function dateRange(content: Record<string, unknown>): string {
  const start = str(content, 'startDate');
  const end = bool(content, 'current') ? '—' : str(content, 'endDate');
  if (!start && !end) return '';
  return [start, end].filter(Boolean).join(' → ');
}
