# Módulo Resumes

**Propósito:** builder estruturado de currículos com **export PDF no cliente** (custo zero de servidor) e suporte a **upload de PDF externo** com URL assinada privada.

## Endpoints

| Método | Rota | Handler | Throttle |
|---|---|---|---|
| `GET` | `/api/resumes` | `ResumeController@index` | `auth:sanctum` |
| `POST` | `/api/resumes` | `ResumeController@store` | `auth:sanctum` |
| `GET` | `/api/resumes/{id}` | `ResumeController@show` | `auth:sanctum` |
| `PUT` | `/api/resumes/{id}` | `ResumeController@update` | `auth:sanctum` |
| `DELETE` | `/api/resumes/{id}` | `ResumeController@destroy` | `auth:sanctum` |
| `POST` | `/api/resumes/pdf` | `ResumePdfController@store` | `throttle:uploads` |
| `GET` | `/api/resumes/{id}/download` | `ResumePdfController@download` | `auth:sanctum` |

## Backend

**Controllers:**
- `app/Http/Controllers/Api/ResumeController.php`
- `app/Http/Controllers/Api/ResumePdfController.php`

**Domínio:** `app/Domain/Resume/`
- **Actions:** `CreateResume`, `UpdateResume`, `UploadResumePdf`
- **DTOs:** `ResumeData`, `ResumeSectionData`
- **Query:** `ListUserResumes`

**Enums:** `app/Enums/ResumeSectionType.php` — `summary | experience | education | skill | language | project | contact`

**Models:** `app/Models/Resume.php`, `ResumeSection.php`

**Policy:** `app/Policies/ResumePolicy.php` — `view/update/delete` se `user_id` bate

**Form Requests:** validam ownership via `Rule::exists('resumes', 'id')->where('user_id', $userId)` quando o currículo é vinculado a uma candidatura.

### Fluxo — Builder

- `CreateResume` em transação:
  1. Cria `Resume` (`title`, `language`, `is_pdf_upload=false`)
  2. Insere `ResumeSection` para cada item (`type`, `order`, `content` JSON)
- `UpdateResume`:
  - `update(...)` no `Resume`
  - `delete()` em todas as sections + insert das novas (mais simples que diff para reordenação)

### Fluxo — Upload PDF externo

`UploadResumePdf`:
1. Valida `mimes:pdf, max:5MB`
2. Gera `path = "resumes/{user_id}/{uuid}.pdf"` em disk `s3` com `visibility=private`
3. Cria `Resume` com `is_pdf_upload=true`, `file_path`, `metadata = { original_name, size }`

### Fluxo — Download

`ResumePdfController@download`:
- Retorna `temporaryUrl(now()->addMinutes(5))` via S3
- **Fallback:** `disk->url()` para `Storage::fake()` em testes (driver fake não suporta `temporaryUrl`)

## Frontend

**Arquivos:** `frontend/src/modules/resumes/`

### Views
- **`ResumesListView`** — cards com título/data/ações (editar, exportar, baixar, excluir). Botões topo: **Novo**, **Upload PDF** (form com file input). Delete confirma.
- **`ResumeBuilderView`** — modos `new`/`edit` (route param). **Template chooser** para currículos novos: botão "Modelo padrão" (preenche com seções pré-definidas: 1 summary, 2 experiences, 1 education, 5 skills, 2 languages) ou "Em branco". Form: `title`, `language`, e **array dinâmico de seções**. Helper `emptyContentFor(type)` retorna template do `content`. Por seção: campos específicos (experience → company/role/dates/description; skill/language → name/level via select). Controles ↑ ↓ remove +. Save via `useSaveResume`. Flash de sucesso 3s. Sidebar de tipos de seção **não** expande com o conteúdo principal (removido `flex-1`).
- **`ResumeExportView`** — seletor de template (Clássico/Moderno) + preview. Botão **Exportar PDF** dispara `useResumePdfExport.exportToPdf(elementRef, filename)`.

### Templates de PDF
- **`ClassicTemplate.vue`** — serif, layout single-column
- **`ModernTemplate.vue`** — sidebar indigo + main column
- Ambos renderizam `<article class="pdf-page">` com dimensões A4 (794×1123 px).

### Helpers (`templates/helpers.ts`)
- `sectionsByType(type)` — filtra seções por tipo
- `str(content, key)`, `bool(content, key)` — acesso tipado ao content JSON
- `dateRange(content)` — formata intervalo de datas. Se `content.current=true`, exibe label localizado ("Atual"/"Present"/"Actual") baseado em `navigator.language` (via `CURRENT_LABELS`)
- `levelLabel(level)` — traduz níveis de skill/language (beginner/intermediate/advanced/native/fluent) para o idioma do navegador via `LEVEL_LABELS`. Usado nos dois templates para seções `skill` e `language`.

### Composables
- **`useResumes`** — query `['resumes']`, GET `/api/resumes`
- **`useResumeDetail`** — query `['resume', id]`, condicional (enabled if `id > 0`)
- **`useSaveResume`** — POST/PUT, payload `{ title, language, sections: [{type, order, content}] }`
- **`useDeleteResume`** — DELETE
- **`useUploadResumePdf`** — POST `/api/resumes/pdf` (FormData)
- **`useResumePdfExport`** — **import dinâmico** de `jspdf` (chunk separado, fora do bundle inicial). Recebe `{ resume, template, userName, filename }` (não recebe DOM). Renderiza **PDF vetorial** desenhando primitivas (`pdf.text`, `pdf.line`, `pdf.rect`, `splitTextToSize`) — texto selecionável, leve (~30-80kb), qualidade infinita no zoom. Dois renderers: `renderClassic` (single column) e `renderModern` (sidebar verde + main column, sidebar redesenhada em cada página adicional). Multi-página com `ensureSpace(needed)` que cria nova página antes de elementos que não cabem.

## Efeitos colaterais

- Escritas: `resumes`, `resume_sections`
- Objetos em S3/MinIO (uploads)

## Testes

- `backend/tests/Feature/Resumes/` — `ModelTest`, `ListResumesTest`, `ShowResumeTest`, `CreateResumeTest`, `UpdateResumeTest`, `DeleteResumeTest`, `PolicyTest`, `UploadResumePdfTest`, `DownloadResumePdfTest`
- `frontend/tests/resumeSchemas.test.ts`
- `frontend/tests/useSaveResume.test.ts`
- `frontend/tests/useUploadResumePdf.test.ts`

## Pontos de atenção

- **`UpdateResume` apaga e reinsere sections.** Se isso causar perda de IDs estáveis em algum cliente, trocar por diff. Por ora, simplifica reordenação.
- **`temporaryUrl` exige driver S3-compatible.** MinIO funciona; `Storage::fake()` não. O fallback no `download` é especificamente para teste — não usar em produção.
- **jsPDF infla o bundle** (~200kb). `useResumePdfExport` faz `await import('jspdf')` dinâmico — não mover esse import para o topo.
- **Encoding WinAnsi (CP1252) das fontes built-in do jsPDF** cobre acentos pt-BR/es (`ã ç é í ó ú ñ`), en-dash (`–`) e middle dot (`·`). Caracteres fora do encoding (emoji, CJK, seta `→`) viram `?`. Por isso `localDateRange` no composable usa `–` em vez de `→` (que aparece no preview Vue).
- **Templates Vue (Classic/Modern) são apenas preview na tela.** O export NÃO usa o DOM — desenha do zero a partir do `Resume`. Mudanças visuais nos templates Vue não refletem no PDF; mexer no PDF exige editar `renderClassic`/`renderModern` no `useResumePdfExport`.
- **Multi-página automática:** `ensureSpace(needed, pageBottom, ...)` quebra antes de cada item se o conteúdo não cabe. No template Modern, a sidebar verde é repintada em cada nova página via callback `paintModernSidebar`.
- **Tradução de níveis no PDF** depende de `navigator.language`. Se o navegador estiver em idioma não mapeado (fora de pt/en/es), cai em fallback inglês. Para adicionar idiomas, editar `LEVEL_LABELS` e `CURRENT_LABELS` em `helpers.ts`.
- **Template chooser** só aparece em currículos novos (`mode === 'new'`). Se o user recarregar a página antes de escolher, o chooser reaparece. Após escolher, a flag `templateChosen` impede re-exibição.
- **Tamanho máximo de upload (5MB)** está no Form Request E no `php.ini` do container (`upload_max_filesize`). Se mudar, ajustar nos dois.
- **Visibilidade `private` no S3:** se alguém setar `public-read` na bucket policy (R2/MinIO), o `temporaryUrl` ainda funciona mas qualquer URL direta também — quebra a privacidade. Conferir bucket policy ao provisionar.
- **Vinculação a candidatura:** o Form Request da Application valida ownership via `Rule::exists ... where user_id`. Não remover o `where`.
- **`is_pdf_upload=true`** desabilita o builder (o front renderiza só "baixar/excluir" para esses). Se aparecer botão "editar" em PDF upload, é regressão na UI.
- **Seeding do form a partir do `useQuery` precisa deep clone.** TanStack Vue Query 5 retorna `data` como proxy **readonly**. Spread shallow (`[...resume.sections]`) compartilha referências readonly — `setContent` falha silencioso (`[Vue warn] target is readonly`), input mantém valor digitado (DOM), mas `sections.value` continua original e o save manda o estado antigo. No `ResumeBuilderView` fazemos `resume.sections.map(s => ({ ...s, content: { ...s.content } }))` antes de atribuir. Regression: `frontend/tests/resumeBuilderView.test.ts`.
- **`CreateResume` precisa setar `file_path` e `metadata` explicitamente.** `Resume::create([...])` não puxa defaults do banco (Eloquent não auto-refresh). Sem isso a resposta JSON do POST sai sem essas chaves, e o `ResumeSchema` no front (`z.string().nullable()` sem `.optional()`) faz throw em `parse()` — mutation rejeita, `onSave` mostra "Falha ao salvar" mesmo com 201. Regression: `tests/Feature/Resumes/CreateResumeTest.php` (`returns file_path and metadata in response`).
