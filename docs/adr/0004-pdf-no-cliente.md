# 4. Geração de PDF no cliente

Data: 2026-04-17
Status: Aceita

## Contexto

Builder de currículos precisa exportar PDF. Opções: gerar no servidor (Chromium headless via Browsershot) ou no cliente (jsPDF/pdf-lib).

## Decisão

Geração 100% no cliente via `jsPDF` + `html2canvas` (ou `pdf-lib` para templates compostos). Templates são componentes Vue renderizados em DOM oculto e convertidos em PDF pelo browser.

## Alternativas consideradas

- **Browsershot (server):** fidelidade máxima, suporta qualquer CSS. Custo de CPU/RAM + instalar Chromium no container + worker dedicado para não bloquear requests.
- **Híbrido:** preview client, export server. Complexidade dobrada.

## Consequências

- **+** Custo zero de servidor (escala infinitamente com usuários)
- **+** Latência zero (arquivo baixa direto)
- **+** Privacidade: dados do currículo nunca saem do browser até o usuário baixar
- **−** Qualidade de render varia por browser (mitigado com testes em Chrome/Firefox/Safari)
- **−** Templates complexos com CSS avançado (grid complexo, svg inline) podem ter artefatos
- **−** Se fidelidade virar problema, podemos adicionar fallback server-side depois
