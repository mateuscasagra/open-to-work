/**
 * Formata centavos em BRL (R$). Usa a locale do navegador como fallback
 * pra escolher separador decimal/milhar; resultado sempre em BRL.
 *
 *   formatBrl(2500)         → "R$ 25,00"
 *   formatBrl(2500, true)   → "R$ 25"   (compact, sem decimais quando inteiro)
 */
export function formatBrl(cents: number, compactIfInteger = false): string {
  const value = cents / 100;
  const isInteger = Number.isInteger(value);

  const formatter = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    minimumFractionDigits: compactIfInteger && isInteger ? 0 : 2,
    maximumFractionDigits: 2,
  });

  return formatter.format(value);
}
