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

**Enums:** `app/Enums/ResumeSectionType.php` — `summary | experience | education | skill | language | project`

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
- **`ResumeBuilderView`** — modos `new`/`edit` (route param). Form: `title`, `language`, e **array dinâmico de seções**. Helper `emptyContentFor(type)` retorna template do `content`. Por seção: campos específicos (experience → company/role/dates/description; education → institution/degree/field/dates; etc.). Controles ↑ ↓ remove +. Save via `useSaveResume`. Flash de sucesso 3s.
- **`ResumeExportView`** — seletor de template (Clássico/Moderno) + preview. Botão **Exportar PDF** dispara `useResumePdfExport.exportToPdf(elementRef, filename)`.

### Templates de PDF
- **`ClassicTemplate.vue`** — serif, layout single-column
- **`ModernTemplate.vue`** — sidebar indigo + main column
- Ambos renderizam `<article class="pdf-page">` com dimensões A4 (794×1123 px). Helpers: `sectionsByType(type)`, `dateRange(s, e)`, `str(content, key)`.

### Composables
- **`useResumes`** — query `['resumes']`, GET `/api/resumes`
- **`useResumeDetail`** — query `['resume', id]`, condicional (enabled if `id > 0`)
- **`useSaveResume`** — POST/PUT, payload `{ title, language, sections: [{type, order, content}] }`
- **`useDeleteResume`** — DELETE
- **`useUploadResumePdf`** — POST `/api/resumes/pdf` (FormData)
- **`useResumePdfExport`** — **imports dinâmicos** de `html2canvas` e `jspdf` (chunk separado, fora do bundle inicial). `html2canvas(el, { scale: 2, backgroundColor: '#fff' })` → `jsPDF('p', 'mm', 'a4')`. **Multi-página automática** (loop em `heightLeft`). Filename com timestamp.

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
- **html2canvas + jsPDF inflam o bundle** (~500kb). Por isso o `useResumePdfExport` faz `await import(...)` dinâmico. Não mover esse import para o topo.
- **html2canvas tem problemas com fontes carregadas via CSS @font-face** se ainda não estiverem prontas. O export espera `document.fonts.ready` antes de renderizar (verificar — caso contrário, fontes vêm como fallback do sistema).
- **Multi-página:** o loop em `heightLeft` corta no meio de elementos se o conteúdo for muito alto sem quebras. Se reportarem texto cortado, considerar `pagebreak-inside: avoid` em headings.
- **Tamanho máximo de upload (5MB)** está no Form Request E no `php.ini` do container (`upload_max_filesize`). Se mudar, ajustar nos dois.
- **Visibilidade `private` no S3:** se alguém setar `public-read` na bucket policy (R2/MinIO), o `temporaryUrl` ainda funciona mas qualquer URL direta também — quebra a privacidade. Conferir bucket policy ao provisionar.
- **Vinculação a candidatura:** o Form Request da Application valida ownership via `Rule::exists ... where user_id`. Não remover o `where`.
- **`is_pdf_upload=true`** desabilita o builder (o front renderiza só "baixar/excluir" para esses). Se aparecer botão "editar" em PDF upload, é regressão na UI.
