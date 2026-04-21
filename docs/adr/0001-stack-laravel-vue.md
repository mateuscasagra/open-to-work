# 1. Stack Laravel + Vue

Data: 2026-04-17
Status: Aceita

## Contexto

Precisamos escolher a stack principal. Requisitos: custo baixo, escalabilidade, produtividade, experiência do dev, ecossistema maduro.

## Decisão

- **Backend:** Laravel 11 (PHP 8.3) — ecossistema maduro, Eloquent/Horizon/Sanctum/Socialite cobrem 80% das necessidades, comunidade ativa, hosting barato.
- **Frontend:** Vue 3 + TypeScript + Vite — curva suave, Composition API, Pinia, excelente DX.

## Alternativas consideradas

- Next.js + Node: ecossistema grande mas stack fragmentada (auth, queue, ORM), custo mental maior.
- Django + React: Python maduro, mas menos ferramentas "batteries-included" que Laravel para este escopo.
- Rails + Hotwire: produtivo, mas menor pool de devs BR familiarizados.

## Consequências

- **+** Velocidade de desenvolvimento alta no MVP
- **+** Sanctum + Socialite + Horizon reduzem drasticamente trabalho de infra
- **+** Hosting PHP é comodity (qualquer VPS roda)
- **−** Vue tem ecossistema menor que React
- **−** PHP 8.3 ainda tem estigma em algumas empresas (não afeta projeto próprio)
