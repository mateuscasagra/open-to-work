import { reactive, watch } from 'vue';

export interface ColumnStyle {
  accent: string;
  bg: string;
  text: string;
  border: string;
}

export interface ColumnConfig {
  status: string;
  label: string;
  color: string;
  visible: boolean;
}

const PRESET_TO_HEX: Record<string, string> = {
  slate: '#64748b', teal: '#14b8a6', pink: '#ec4899', cyan: '#06b6d4',
  orange: '#f97316', amber: '#f59e0b', emerald: '#10b981', red: '#ef4444',
  violet: '#8b5cf6', blue: '#3b82f6', lime: '#84cc16', rose: '#f43f5e',
  indigo: '#6366f1',
};

const LOCKED_STATUSES = new Set(['applied', 'accepted', 'rejected']);

const DEFAULT_COLUMNS: ColumnConfig[] = [
  { status: 'applied',        label: '', color: '#64748b', visible: true },
  { status: 'screening',      label: '', color: '#14b8a6', visible: true },
  { status: 'assessment',     label: '', color: '#ec4899', visible: true },
  { status: 'interview_hr',   label: '', color: '#06b6d4', visible: true },
  { status: 'interview_tech', label: '', color: '#f97316', visible: true },
  { status: 'offer',          label: '', color: '#f59e0b', visible: true },
  { status: 'accepted',       label: '', color: '#10b981', visible: true },
  { status: 'rejected',       label: '', color: '#ef4444', visible: true },
  { status: 'withdrawn',      label: '', color: '#64748b', visible: false },
];

const DEFAULT_STATUSES = new Set(DEFAULT_COLUMNS.map(c => c.status));
export const MAX_COLUMNS = 15;
const STORAGE_KEY = 'kanban-config';

function normalizeColor(color: string): string {
  return PRESET_TO_HEX[color] ?? color;
}

function hexToRgb(hex: string): [number, number, number] {
  const h = hex.replace('#', '');
  return [
    parseInt(h.substring(0, 2), 16) || 100,
    parseInt(h.substring(2, 4), 16) || 116,
    parseInt(h.substring(4, 6), 16) || 139,
  ];
}

function load(): ColumnConfig[] {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return structuredClone(DEFAULT_COLUMNS);
    const parsed = JSON.parse(raw) as ColumnConfig[];
    if (!Array.isArray(parsed) || parsed.length === 0) return structuredClone(DEFAULT_COLUMNS);

    for (const col of parsed) {
      col.color = normalizeColor(col.color);
    }

    const existing = new Set(parsed.map(c => c.status));
    for (const def of DEFAULT_COLUMNS) {
      if (!existing.has(def.status)) {
        parsed.push({ ...def, visible: false });
      }
    }
    return parsed;
  } catch {
    return structuredClone(DEFAULT_COLUMNS);
  }
}

function save(columns: ColumnConfig[]): void {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(columns));
}

const columns = reactive<ColumnConfig[]>(load());

watch(columns, (val) => save(val), { deep: true });

export function useKanbanConfig() {
  const visibleColumns = () => columns.filter(c => c.visible);

  function styleFor(col: ColumnConfig): ColumnStyle {
    const [r, g, b] = hexToRgb(col.color);
    const dr = Math.round(r * 0.6);
    const dg = Math.round(g * 0.6);
    const db = Math.round(b * 0.6);
    return {
      accent: col.color,
      bg: `rgba(${r}, ${g}, ${b}, 0.08)`,
      text: `rgb(${dr}, ${dg}, ${db})`,
      border: `rgba(${r}, ${g}, ${b}, 0.2)`,
    };
  }

  function isLocked(status: string): boolean {
    return LOCKED_STATUSES.has(status);
  }

  function isCustom(status: string): boolean {
    return !DEFAULT_STATUSES.has(status);
  }

  function rename(status: string, label: string): void {
    if (isLocked(status)) return;
    const col = columns.find(c => c.status === status);
    if (col) col.label = label;
  }

  function setColor(status: string, color: string): void {
    if (isLocked(status)) return;
    const col = columns.find(c => c.status === status);
    if (col) col.color = color;
  }

  function toggleVisible(status: string): void {
    const col = columns.find(c => c.status === status);
    if (col) col.visible = !col.visible;
  }

  function moveColumn(status: string, direction: -1 | 1): void {
    if (isLocked(status)) return;
    const idx = columns.findIndex(c => c.status === status);
    const target = idx + direction;
    if (idx < 0 || target < 0 || target >= columns.length) return;
    if (isLocked(columns[target].status)) return;
    const temp = columns[idx];
    columns[idx] = columns[target];
    columns[target] = temp;
  }

  function addColumn(label: string, color: string): string | null {
    if (columns.length >= MAX_COLUMNS) return null;
    const id = `custom_${Date.now()}`;
    const endIdx = columns.findIndex(c => c.status === 'accepted');
    const insertIdx = endIdx >= 0 ? endIdx : columns.length;
    columns.splice(insertIdx, 0, { status: id, label, color, visible: true });
    return id;
  }

  function removeColumn(status: string): void {
    if (isLocked(status)) return;
    const idx = columns.findIndex(c => c.status === status);
    if (idx >= 0) columns.splice(idx, 1);
  }

  function resetDefaults(): void {
    columns.splice(0, columns.length, ...structuredClone(DEFAULT_COLUMNS));
  }

  return {
    columns, visibleColumns, styleFor, rename, setColor, toggleVisible,
    moveColumn, resetDefaults, isLocked, isCustom, addColumn, removeColumn,
  };
}
