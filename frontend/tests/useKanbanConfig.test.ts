import { describe, it, expect, beforeEach } from 'vitest';
import { useKanbanConfig, MAX_COLUMNS } from '@/modules/applications/composables/useKanbanConfig';

function getConfig() {
  return useKanbanConfig();
}

describe('useKanbanConfig', () => {
  beforeEach(() => {
    localStorage.clear();
    getConfig().resetDefaults();
  });

  it('loads default columns on first use', () => {
    const { columns } = getConfig();
    expect(columns.length).toBe(9);
    expect(columns[0].status).toBe('applied');
    expect(columns[columns.length - 2].status).toBe('rejected');
  });

  it('MAX_COLUMNS is 15', () => {
    expect(MAX_COLUMNS).toBe(15);
  });

  it('stores colors as hex values', () => {
    const { columns } = getConfig();
    for (const col of columns) {
      expect(col.color).toMatch(/^#[0-9a-fA-F]{6}$/);
    }
  });

  it('visibleColumns excludes hidden columns', () => {
    const { visibleColumns, columns } = getConfig();
    const withdrawn = columns.find(c => c.status === 'withdrawn');
    expect(withdrawn?.visible).toBe(false);
    expect(visibleColumns().every(c => c.visible)).toBe(true);
    expect(visibleColumns().length).toBe(columns.filter(c => c.visible).length);
  });

  describe('isLocked', () => {
    it('returns true for applied, accepted, rejected', () => {
      const { isLocked } = getConfig();
      expect(isLocked('applied')).toBe(true);
      expect(isLocked('accepted')).toBe(true);
      expect(isLocked('rejected')).toBe(true);
    });

    it('returns false for other statuses', () => {
      const { isLocked } = getConfig();
      expect(isLocked('screening')).toBe(false);
      expect(isLocked('offer')).toBe(false);
      expect(isLocked('custom_123')).toBe(false);
    });
  });

  describe('isCustom', () => {
    it('returns false for default statuses', () => {
      const { isCustom } = getConfig();
      expect(isCustom('applied')).toBe(false);
      expect(isCustom('screening')).toBe(false);
      expect(isCustom('withdrawn')).toBe(false);
    });

    it('returns true for custom statuses', () => {
      const { isCustom } = getConfig();
      expect(isCustom('custom_123')).toBe(true);
      expect(isCustom('my_stage')).toBe(true);
    });
  });

  describe('rename', () => {
    it('renames a non-locked column', () => {
      const { columns, rename } = getConfig();
      rename('screening', 'Phone Screen');
      expect(columns.find(c => c.status === 'screening')?.label).toBe('Phone Screen');
    });

    it('does not rename a locked column', () => {
      const { columns, rename } = getConfig();
      rename('applied', 'Sent');
      expect(columns.find(c => c.status === 'applied')?.label).toBe('');
    });
  });

  describe('setColor', () => {
    it('sets color on a non-locked column', () => {
      const { columns, setColor } = getConfig();
      setColor('screening', '#ff0000');
      expect(columns.find(c => c.status === 'screening')?.color).toBe('#ff0000');
    });

    it('does not set color on a locked column', () => {
      const { columns, setColor } = getConfig();
      const original = columns.find(c => c.status === 'applied')!.color;
      setColor('applied', '#ff0000');
      expect(columns.find(c => c.status === 'applied')?.color).toBe(original);
    });
  });

  describe('moveColumn', () => {
    it('moves a column forward', () => {
      const { columns, moveColumn } = getConfig();
      const before = columns[1].status;
      const after = columns[2].status;
      moveColumn(before, 1);
      expect(columns[2].status).toBe(before);
      expect(columns[1].status).toBe(after);
    });

    it('does not move a locked column', () => {
      const { columns, moveColumn } = getConfig();
      moveColumn('applied', 1);
      expect(columns[0].status).toBe('applied');
    });

    it('does not swap with a locked column', () => {
      const { columns, moveColumn } = getConfig();
      const beforeAccepted = columns.findIndex(c => c.status === 'accepted') - 1;
      const status = columns[beforeAccepted].status;
      moveColumn(status, 1);
      expect(columns[beforeAccepted].status).toBe(status);
    });
  });

  describe('addColumn', () => {
    it('adds a custom column before accepted', () => {
      const { columns, addColumn } = getConfig();
      const countBefore = columns.length;
      const id = addColumn('Follow-up', '#3b82f6');
      expect(id).toBeTruthy();
      expect(columns.length).toBe(countBefore + 1);
      const acceptedIdx = columns.findIndex(c => c.status === 'accepted');
      const newIdx = columns.findIndex(c => c.status === id);
      expect(newIdx).toBeLessThan(acceptedIdx);
    });

    it('returns null when at max capacity', () => {
      const { columns, addColumn } = getConfig();
      while (columns.length < MAX_COLUMNS) {
        addColumn(`Stage ${columns.length}`, '#000000');
      }
      const result = addColumn('One More', '#ffffff');
      expect(result).toBeNull();
      expect(columns.length).toBe(MAX_COLUMNS);
    });

    it('marks custom column as visible', () => {
      const { columns, addColumn } = getConfig();
      const id = addColumn('Test', '#aabbcc');
      const col = columns.find(c => c.status === id);
      expect(col?.visible).toBe(true);
      expect(col?.label).toBe('Test');
      expect(col?.color).toBe('#aabbcc');
    });
  });

  describe('removeColumn', () => {
    it('removes a custom column from the array', () => {
      const { columns, addColumn, removeColumn } = getConfig();
      const id = addColumn('Temp', '#000000')!;
      const countBefore = columns.length;
      removeColumn(id);
      expect(columns.length).toBe(countBefore - 1);
      expect(columns.find(c => c.status === id)).toBeUndefined();
    });

    it('removes a default non-locked column from the array', () => {
      const { columns, removeColumn } = getConfig();
      const countBefore = columns.length;
      removeColumn('screening');
      expect(columns.length).toBe(countBefore - 1);
      expect(columns.find(c => c.status === 'screening')).toBeUndefined();
    });

    it('does not remove a locked column', () => {
      const { columns, removeColumn } = getConfig();
      const countBefore = columns.length;
      removeColumn('applied');
      removeColumn('accepted');
      removeColumn('rejected');
      expect(columns.length).toBe(countBefore);
    });
  });

  describe('resetDefaults', () => {
    it('restores all default columns', () => {
      const { columns, removeColumn, addColumn, resetDefaults } = getConfig();
      removeColumn('screening');
      addColumn('Custom', '#000000');
      resetDefaults();
      expect(columns.length).toBe(9);
      expect(columns[0].status).toBe('applied');
      expect(columns.find(c => c.status === 'screening')).toBeTruthy();
    });
  });

  describe('styleFor', () => {
    it('returns computed styles from hex color', () => {
      const { columns, styleFor } = getConfig();
      const style = styleFor(columns[0]);
      expect(style.accent).toMatch(/^#/);
      expect(style.bg).toMatch(/^rgba\(/);
      expect(style.text).toMatch(/^rgb\(/);
      expect(style.border).toMatch(/^rgba\(/);
    });
  });

  describe('state consistency', () => {
    it('all calls return the same columns reference', () => {
      const a = getConfig();
      const b = getConfig();
      expect(a.columns).toBe(b.columns);
    });

    it('resetDefaults restores hex colors', () => {
      const { columns, setColor, resetDefaults } = getConfig();
      setColor('screening', '#ff00ff');
      resetDefaults();
      for (const col of columns) {
        expect(col.color).toMatch(/^#[0-9a-fA-F]{6}$/);
      }
    });
  });
});
