import { ref } from 'vue';

export function useResumePdfExport() {
  const exporting = ref(false);
  const error = ref<string | null>(null);

  async function exportToPdf(element: HTMLElement, filename: string): Promise<void> {
    exporting.value = true;
    error.value = null;
    try {
      const [{ default: html2canvas }, { default: jsPDF }] = await Promise.all([
        import('html2canvas'),
        import('jspdf'),
      ]);

      const canvas = await html2canvas(element, {
        scale: 2,
        backgroundColor: '#ffffff',
        useCORS: true,
      });

      const pdf = new jsPDF({
        orientation: 'portrait',
        unit: 'mm',
        format: 'a4',
      });

      const pageWidth = pdf.internal.pageSize.getWidth();
      const pageHeight = pdf.internal.pageSize.getHeight();
      const imgWidth = pageWidth;
      const imgHeight = (canvas.height * imgWidth) / canvas.width;

      let heightLeft = imgHeight;
      let position = 0;
      const imgData = canvas.toDataURL('image/png');

      pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
      heightLeft -= pageHeight;

      while (heightLeft > 0) {
        position -= pageHeight;
        pdf.addPage();
        pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
        heightLeft -= pageHeight;
      }

      pdf.save(filename.endsWith('.pdf') ? filename : `${filename}.pdf`);
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Export failed';
      throw e;
    } finally {
      exporting.value = false;
    }
  }

  return { exportToPdf, exporting, error };
}
