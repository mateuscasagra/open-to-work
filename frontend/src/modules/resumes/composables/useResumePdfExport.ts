import { ref } from 'vue';
import type { jsPDF } from 'jspdf';
import type { Resume, ResumeSection, ResumeSectionType } from '@/shared/api/schemas';
import { levelLabel, sectionLabel, str } from '../templates/helpers';

type PdfDoc = jsPDF;

type TemplateKey = 'classic' | 'modern';

interface ExportOptions {
  resume: Resume;
  template: TemplateKey;
  userName?: string;
  filename?: string;
}

type RGB = readonly [number, number, number];

const PAGE_W_MM = 210;
const PAGE_H_MM = 297;

const COLOR_INK_900: RGB = [15, 23, 42];
const COLOR_INK_700: RGB = [51, 65, 85];
const COLOR_INK_600: RGB = [71, 85, 105];
const COLOR_INK_500: RGB = [100, 116, 139];
const COLOR_INK_300: RGB = [203, 213, 225];
const COLOR_EMERALD_700: RGB = [4, 120, 87];
const COLOR_EMERALD_200: RGB = [167, 243, 208];
const COLOR_WHITE: RGB = [255, 255, 255];

const PT_TO_MM = 0.3528;
const LINE_FACTOR = 1.2;

export function useResumePdfExport() {
  const exporting = ref(false);
  const error = ref<string | null>(null);

  async function exportToPdf(opts: ExportOptions): Promise<void> {
    exporting.value = true;
    error.value = null;
    try {
      const { default: jsPDF } = await import('jspdf');
      const pdf = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });

      if (opts.template === 'modern') {
        renderModern(pdf, opts.resume, opts.userName);
      } else {
        renderClassic(pdf, opts.resume, opts.userName);
      }

      pdf.setProperties({
        title: opts.resume.title,
        subject: 'Resume',
        author: opts.userName ?? '',
        creator: 'Open to Work',
      });

      const baseName = (opts.filename ?? opts.resume.title ?? 'resume').replace(/\s+/g, '_');
      pdf.save(baseName.endsWith('.pdf') ? baseName : `${baseName}.pdf`);
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Export failed';
      throw e;
    } finally {
      exporting.value = false;
    }
  }

  return { exportToPdf, exporting, error };
}

function lineHeight(sizePt: number): number {
  return sizePt * LINE_FACTOR * PT_TO_MM;
}

function sortedSections(resume: Resume, type: ResumeSectionType): ResumeSection[] {
  return resume.sections.filter((s) => s.type === type).sort((a, b) => a.order - b.order);
}

function localDateRange(content: Record<string, unknown>): string {
  const start = str(content, 'startDate');
  const isCurrent = content['current'] === true;
  const lang = navigator.language.slice(0, 2);
  const currentLabel = lang === 'pt' ? 'Atual' : lang === 'es' ? 'Actual' : 'Present';
  const end = isCurrent ? currentLabel : str(content, 'endDate');
  if (!start && !end) return '';
  return [start, end].filter(Boolean).join(' – ');
}

function setText(pdf: PdfDoc, color: RGB): void {
  pdf.setTextColor(color[0], color[1], color[2]);
}
function setDraw(pdf: PdfDoc, color: RGB): void {
  pdf.setDrawColor(color[0], color[1], color[2]);
}
function setFill(pdf: PdfDoc, color: RGB): void {
  pdf.setFillColor(color[0], color[1], color[2]);
}

interface TextOpts {
  fontSize: number;
  bold?: boolean;
  color: RGB;
  maxWidth: number;
}

function writeText(pdf: PdfDoc, text: string, x: number, y: number, opts: TextOpts): number {
  const { fontSize, bold = false, color, maxWidth } = opts;
  if (!text) return y;
  pdf.setFont('helvetica', bold ? 'bold' : 'normal');
  pdf.setFontSize(fontSize);
  setText(pdf, color);
  const lines = pdf.splitTextToSize(text, maxWidth);
  if (lines.length === 0) return y;
  pdf.text(lines, x, y, { baseline: 'top' });
  return y + lines.length * lineHeight(fontSize);
}

function measure(pdf: PdfDoc, text: string, fontSize: number, bold: boolean, maxWidth: number): number {
  if (!text) return 0;
  pdf.setFont('helvetica', bold ? 'bold' : 'normal');
  pdf.setFontSize(fontSize);
  const lines = pdf.splitTextToSize(text, maxWidth);
  return lines.length * lineHeight(fontSize);
}

function ensureSpace(
  pdf: PdfDoc,
  y: number,
  needed: number,
  pageBottom: number,
  topMargin: number,
  onNewPage?: () => void,
): number {
  if (y + needed > pageBottom) {
    pdf.addPage();
    if (onNewPage) onNewPage();
    return topMargin;
  }
  return y;
}

// =================== CLASSIC ===================

function renderClassic(pdf: PdfDoc, resume: Resume, userName?: string): void {
  const MX = 22;
  const TOP = 22;
  const BOTTOM = 22;
  const W = PAGE_W_MM - 2 * MX;
  const PAGE_BOTTOM = PAGE_H_MM - BOTTOM;

  let y = TOP;

  // Header — name
  y = writeText(pdf, userName ?? resume.title, MX, y, {
    fontSize: 22,
    bold: true,
    color: COLOR_INK_900,
    maxWidth: W,
  });
  y += 1;

  // Title
  y = writeText(pdf, resume.title, MX, y, {
    fontSize: 11,
    color: COLOR_INK_600,
    maxWidth: W,
  });
  y += 1.5;

  // Contacts inline
  const contacts = sortedSections(resume, 'contact');
  if (contacts.length > 0) {
    const parts: string[] = [];
    for (const c of contacts) {
      for (const key of ['email', 'phone', 'linkedin', 'github', 'website', 'address']) {
        const v = str(c.content, key);
        if (v) parts.push(v);
      }
    }
    if (parts.length > 0) {
      y = writeText(pdf, parts.join('  ·  '), MX, y, {
        fontSize: 9,
        color: COLOR_INK_600,
        maxWidth: W,
      });
    }
  }

  // Divider
  y += 2.5;
  setDraw(pdf, COLOR_INK_300);
  pdf.setLineWidth(0.2);
  pdf.line(MX, y, MX + W, y);
  y += 5;

  const BODY: ResumeSectionType[] = ['summary', 'experience', 'education', 'skill', 'language', 'project'];

  for (const type of BODY) {
    const items = sortedSections(resume, type);
    if (items.length === 0) continue;

    y = ensureSpace(pdf, y, 14, PAGE_BOTTOM, TOP);

    if (type !== 'summary') {
      y = writeText(pdf, sectionLabel(type).toUpperCase(), MX, y, {
        fontSize: 9,
        bold: true,
        color: COLOR_INK_500,
        maxWidth: W,
      });
      y += 1.5;
    }

    for (const item of items) {
      y = renderClassicItem(pdf, item, type, y, MX, W, PAGE_BOTTOM, TOP);
    }
    y += 3;
  }
}

function renderClassicItem(
  pdf: PdfDoc,
  item: ResumeSection,
  type: ResumeSectionType,
  y: number,
  x: number,
  w: number,
  pageBottom: number,
  topMargin: number,
): number {
  const c = item.content;

  if (type === 'summary') {
    y = writeText(pdf, str(c, 'text'), x, y, {
      fontSize: 10,
      color: COLOR_INK_700,
      maxWidth: w,
    });
    y += 1.5;
    return y;
  }

  if (type === 'experience') {
    const role = str(c, 'role');
    const company = str(c, 'company');
    const dates = localDateRange(c);
    const description = str(c, 'description');

    const head = company ? `${role} — ${company}` : role;
    const dateW = dates ? pdf.getTextWidth(dates) + 2 : 0;
    const headW = w - dateW;

    const headHeight = measure(pdf, head, 10.5, true, headW);
    const descHeight = description ? measure(pdf, description, 10, false, w) : 0;
    y = ensureSpace(pdf, y, headHeight + descHeight + 3, pageBottom, topMargin);

    const rowTop = y;
    const newY = writeText(pdf, head, x, y, {
      fontSize: 10.5,
      bold: true,
      color: COLOR_INK_900,
      maxWidth: headW,
    });

    if (dates) {
      pdf.setFont('helvetica', 'normal');
      pdf.setFontSize(9);
      setText(pdf, COLOR_INK_500);
      pdf.text(dates, x + w, rowTop + 0.6, { baseline: 'top', align: 'right' });
    }
    y = newY;

    if (description) {
      y += 0.8;
      y = writeText(pdf, description, x, y, {
        fontSize: 10,
        color: COLOR_INK_700,
        maxWidth: w,
      });
    }
    y += 2.5;
    return y;
  }

  if (type === 'education') {
    const degree = str(c, 'degree');
    const institution = str(c, 'institution');
    const dates = localDateRange(c);

    const dateW = dates ? pdf.getTextWidth(dates) + 2 : 0;
    const headW = w - dateW;

    const headHeight = measure(pdf, degree, 10.5, true, headW);
    const instHeight = institution ? measure(pdf, institution, 10, false, w) : 0;
    y = ensureSpace(pdf, y, headHeight + instHeight + 3, pageBottom, topMargin);

    const rowTop = y;
    const afterDegree = writeText(pdf, degree, x, y, {
      fontSize: 10.5,
      bold: true,
      color: COLOR_INK_900,
      maxWidth: headW,
    });
    if (dates) {
      pdf.setFont('helvetica', 'normal');
      pdf.setFontSize(9);
      setText(pdf, COLOR_INK_500);
      pdf.text(dates, x + w, rowTop + 0.6, { baseline: 'top', align: 'right' });
    }
    y = afterDegree;
    if (institution) {
      y += 0.6;
      y = writeText(pdf, institution, x, y, {
        fontSize: 10,
        color: COLOR_INK_700,
        maxWidth: w,
      });
    }
    y += 2;
    return y;
  }

  if (type === 'skill' || type === 'language') {
    const name = str(c, 'name');
    const level = str(c, 'level');
    const text = level ? `${name}  ·  ${levelLabel(level)}` : name;
    y = writeText(pdf, text, x, y, {
      fontSize: 10,
      color: COLOR_INK_700,
      maxWidth: w,
    });
    y += 0.5;
    return y;
  }

  if (type === 'project') {
    const name = str(c, 'name');
    const url = str(c, 'url');
    const description = str(c, 'description');
    const head = url ? `${name}  ·  ${url}` : name;

    const headHeight = measure(pdf, head, 10.5, true, w);
    const descHeight = description ? measure(pdf, description, 10, false, w) : 0;
    y = ensureSpace(pdf, y, headHeight + descHeight + 2, pageBottom, topMargin);

    y = writeText(pdf, head, x, y, {
      fontSize: 10.5,
      bold: true,
      color: COLOR_INK_900,
      maxWidth: w,
    });
    if (description) {
      y += 0.6;
      y = writeText(pdf, description, x, y, {
        fontSize: 10,
        color: COLOR_INK_700,
        maxWidth: w,
      });
    }
    y += 2;
    return y;
  }

  return y;
}

// =================== MODERN ===================

const MODERN_SIDEBAR_W = 70;
const MODERN_PAD = 8;
const MODERN_MAIN_X = MODERN_SIDEBAR_W + 14;
const MODERN_MAIN_W = PAGE_W_MM - MODERN_MAIN_X - 14;
const MODERN_TOP = 16;
const MODERN_BOTTOM = 16;

function paintModernSidebar(pdf: PdfDoc): void {
  setFill(pdf, COLOR_EMERALD_700);
  pdf.rect(0, 0, MODERN_SIDEBAR_W, PAGE_H_MM, 'F');
}

function renderModern(pdf: PdfDoc, resume: Resume, userName?: string): void {
  paintModernSidebar(pdf);

  // ----- Sidebar content (page 1 only) -----
  const SIDEBAR_X = MODERN_PAD;
  const SIDEBAR_W = MODERN_SIDEBAR_W - 2 * MODERN_PAD;
  let sy = MODERN_TOP;

  sy = writeText(pdf, userName ?? resume.title, SIDEBAR_X, sy, {
    fontSize: 18,
    bold: true,
    color: COLOR_WHITE,
    maxWidth: SIDEBAR_W,
  });
  sy += 1;
  sy = writeText(pdf, resume.title, SIDEBAR_X, sy, {
    fontSize: 9.5,
    color: COLOR_EMERALD_200,
    maxWidth: SIDEBAR_W,
  });
  sy += 4;

  const SIDEBAR_TYPES: ResumeSectionType[] = ['contact', 'skill', 'language'];
  for (const type of SIDEBAR_TYPES) {
    const items = sortedSections(resume, type);
    if (items.length === 0) continue;

    sy = writeText(pdf, sectionLabel(type).toUpperCase(), SIDEBAR_X, sy, {
      fontSize: 8.5,
      bold: true,
      color: COLOR_EMERALD_200,
      maxWidth: SIDEBAR_W,
    });
    sy += 1.5;

    if (type === 'contact') {
      for (const ct of items) {
        for (const key of ['email', 'phone', 'linkedin', 'github', 'website']) {
          const v = str(ct.content, key);
          if (v) {
            sy = writeText(pdf, v, SIDEBAR_X, sy, {
              fontSize: 9,
              color: COLOR_WHITE,
              maxWidth: SIDEBAR_W,
            });
            sy += 0.4;
          }
        }
        const addr = str(ct.content, 'address');
        if (addr) {
          sy = writeText(pdf, addr, SIDEBAR_X, sy, {
            fontSize: 8.5,
            color: COLOR_EMERALD_200,
            maxWidth: SIDEBAR_W,
          });
          sy += 0.4;
        }
      }
    } else {
      for (const it of items) {
        const name = str(it.content, 'name');
        const level = str(it.content, 'level');
        sy = writeText(pdf, name, SIDEBAR_X, sy, {
          fontSize: 9.5,
          bold: true,
          color: COLOR_WHITE,
          maxWidth: SIDEBAR_W,
        });
        if (level) {
          sy = writeText(pdf, levelLabel(level), SIDEBAR_X, sy, {
            fontSize: 8.5,
            color: COLOR_EMERALD_200,
            maxWidth: SIDEBAR_W,
          });
        }
        sy += 1;
      }
    }
    sy += 4;
  }

  // ----- Main content -----
  const MAIN_PAGE_BOTTOM = PAGE_H_MM - MODERN_BOTTOM;
  let y = MODERN_TOP;

  const MAIN_TYPES: ResumeSectionType[] = ['summary', 'experience', 'education', 'project'];

  for (const type of MAIN_TYPES) {
    const items = sortedSections(resume, type);
    if (items.length === 0) continue;

    y = ensureSpace(pdf, y, 16, MAIN_PAGE_BOTTOM, MODERN_TOP, () => paintModernSidebar(pdf));

    if (type !== 'summary') {
      y = writeText(pdf, sectionLabel(type).toUpperCase(), MODERN_MAIN_X, y, {
        fontSize: 9,
        bold: true,
        color: COLOR_EMERALD_700,
        maxWidth: MODERN_MAIN_W,
      });
      setDraw(pdf, COLOR_EMERALD_200);
      pdf.setLineWidth(0.3);
      pdf.line(MODERN_MAIN_X, y + 0.5, MODERN_MAIN_X + MODERN_MAIN_W, y + 0.5);
      y += 3.5;
    }

    for (const item of items) {
      y = renderModernMainItem(pdf, item, type, y, MODERN_MAIN_X, MODERN_MAIN_W, MAIN_PAGE_BOTTOM);
    }
    y += 4;
  }
}

function renderModernMainItem(
  pdf: PdfDoc,
  item: ResumeSection,
  type: ResumeSectionType,
  y: number,
  x: number,
  w: number,
  pageBottom: number,
): number {
  const c = item.content;

  if (type === 'summary') {
    y = writeText(pdf, str(c, 'text'), x, y, {
      fontSize: 10,
      color: COLOR_INK_700,
      maxWidth: w,
    });
    y += 1.5;
    return y;
  }

  if (type === 'experience') {
    const role = str(c, 'role');
    const company = str(c, 'company');
    const dates = localDateRange(c);
    const description = str(c, 'description');

    const dateW = dates ? pdf.getTextWidth(dates) + 2 : 0;
    const headW = w - dateW;

    const headHeight = measure(pdf, role, 10.5, true, headW);
    const compHeight = company ? measure(pdf, company, 10, false, w) : 0;
    const descHeight = description ? measure(pdf, description, 10, false, w) : 0;
    y = ensureSpace(pdf, y, headHeight + compHeight + descHeight + 4, pageBottom, MODERN_TOP, () => paintModernSidebar(pdf));

    const rowTop = y;
    const afterRole = writeText(pdf, role, x, y, {
      fontSize: 10.5,
      bold: true,
      color: COLOR_INK_900,
      maxWidth: headW,
    });
    if (dates) {
      pdf.setFont('helvetica', 'normal');
      pdf.setFontSize(9);
      setText(pdf, COLOR_INK_500);
      pdf.text(dates, x + w, rowTop + 0.6, { baseline: 'top', align: 'right' });
    }
    y = afterRole;
    if (company) {
      y += 0.4;
      y = writeText(pdf, company, x, y, {
        fontSize: 10,
        bold: true,
        color: COLOR_EMERALD_700,
        maxWidth: w,
      });
    }
    if (description) {
      y += 0.6;
      y = writeText(pdf, description, x, y, {
        fontSize: 10,
        color: COLOR_INK_700,
        maxWidth: w,
      });
    }
    y += 3;
    return y;
  }

  if (type === 'education') {
    const degree = str(c, 'degree');
    const institution = str(c, 'institution');
    const dates = localDateRange(c);

    const dateW = dates ? pdf.getTextWidth(dates) + 2 : 0;
    const headW = w - dateW;

    const headHeight = measure(pdf, degree, 10.5, true, headW);
    const instHeight = institution ? measure(pdf, institution, 10, false, w) : 0;
    y = ensureSpace(pdf, y, headHeight + instHeight + 3, pageBottom, MODERN_TOP, () => paintModernSidebar(pdf));

    const rowTop = y;
    const afterDegree = writeText(pdf, degree, x, y, {
      fontSize: 10.5,
      bold: true,
      color: COLOR_INK_900,
      maxWidth: headW,
    });
    if (dates) {
      pdf.setFont('helvetica', 'normal');
      pdf.setFontSize(9);
      setText(pdf, COLOR_INK_500);
      pdf.text(dates, x + w, rowTop + 0.6, { baseline: 'top', align: 'right' });
    }
    y = afterDegree;
    if (institution) {
      y += 0.6;
      y = writeText(pdf, institution, x, y, {
        fontSize: 10,
        color: COLOR_INK_700,
        maxWidth: w,
      });
    }
    y += 2.5;
    return y;
  }

  if (type === 'project') {
    const name = str(c, 'name');
    const url = str(c, 'url');
    const description = str(c, 'description');
    const head = url ? `${name}  ·  ${url}` : name;

    const headHeight = measure(pdf, head, 10.5, true, w);
    const descHeight = description ? measure(pdf, description, 10, false, w) : 0;
    y = ensureSpace(pdf, y, headHeight + descHeight + 2, pageBottom, MODERN_TOP, () => paintModernSidebar(pdf));

    y = writeText(pdf, head, x, y, {
      fontSize: 10.5,
      bold: true,
      color: COLOR_INK_900,
      maxWidth: w,
    });
    if (description) {
      y += 0.6;
      y = writeText(pdf, description, x, y, {
        fontSize: 10,
        color: COLOR_INK_700,
        maxWidth: w,
      });
    }
    y += 2;
    return y;
  }

  return y;
}
