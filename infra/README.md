# Infra

Docker Compose pra dev local e prod (VPS Hostinger self-hosted).

## Dev local

```bash
docker compose -f infra/docker-compose.yml up -d
```

| Serviço | Porta | Credenciais |
|---|---|---|
| Postgres | 5432 | opentowork / opentowork |
| Redis | 6379 | — |
| MinIO API | 9000 | opentowork / opentowork_minio |
| MinIO Console | 9001 | ↑ |
| MailHog SMTP | 1025 | — |
| MailHog UI | 8025 | http://localhost:8025 |

Dados persistem em `infra/data/` (gitignored).

---

# Deploy de Produção (VPS Hostinger KVM 1, 4GB RAM)

Setup completo do zero. Siga em ordem.

## 0. Pré-requisitos

- VPS Hostinger com Ubuntu 22.04+ (acesso SSH como `root` ou user com sudo)
- Domínio `opentowork.app.br` registrado
- Repo no GitHub (`mateuscasagra/open-to-work`)
- Conta na Cloudflare (free) pra DNS + R2 storage
- Conta na Resend (free) pra envio de email — opcional na primeira subida

## 1. DNS (Cloudflare ou painel da Hostinger)

Criar dois registros A apontando pro IP da VPS:

| Tipo | Nome | Valor | Proxy |
|---|---|---|---|
| A | `opentowork.app.br` | `<IP_DA_VPS>` | DNS only (cinza) |
| A | `www` | `<IP_DA_VPS>` | DNS only (cinza) |

> Inicialmente deixar **DNS only** (sem proxy laranja Cloudflare) pra o certbot funcionar. Depois pode ativar.

Confirmar propagação:
```bash
dig +short opentowork.app.br
```

## 2. Setup da VPS

SSH na VPS:
```bash
ssh root@<IP_DA_VPS>
```

### 2.1 Atualizar pacotes + criar swap (importante em 4GB)

```bash
apt update && apt upgrade -y
apt install -y curl git ufw

# Swap de 2GB (essencial pra evitar OOM em picos)
fallocate -l 2G /swapfile
chmod 600 /swapfile
mkswap /swapfile
swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab
echo 'vm.swappiness=10' >> /etc/sysctl.conf
sysctl -p
```

### 2.2 Firewall

```bash
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable
```

### 2.3 Docker + Compose plugin

```bash
curl -fsSL https://get.docker.com | sh
systemctl enable --now docker
docker --version
docker compose version
```

### 2.4 Estrutura no VPS

```bash
mkdir -p /opt/opentowork
cd /opt/opentowork
```

Copie os arquivos da pasta `infra/` deste repo pra `/opt/opentowork/`:

```bash
# do seu PC local:
scp -r infra/docker-compose.prod.yml infra/nginx infra/scripts root@<IP>:/opt/opentowork/
ssh root@<IP> "mkdir -p /opt/opentowork/secrets /opt/opentowork/nginx/ssl /opt/opentowork/nginx/certbot"
```

## 3. Login no GitHub Container Registry (GHCR)

A VPS precisa puxar imagens privadas do GHCR. Crie um Personal Access Token com escopo `read:packages`:

1. GitHub → Settings → Developer settings → Personal access tokens → **Tokens (classic)**
2. Generate new token: nome `vps-ghcr-pull`, escopo `read:packages`
3. Na VPS:
   ```bash
   echo "<SEU_PAT>" | docker login ghcr.io -u mateuscasagra --password-stdin
   ```

## 4. Preencher secrets

```bash
cd /opt/opentowork/secrets
# Copie os templates do repo:
scp infra/secrets/*.example root@<IP>:/opt/opentowork/secrets/
ssh root@<IP>
cd /opt/opentowork/secrets
cp backend.env.example backend.env
cp postgres.env.example postgres.env

# Gere uma APP_KEY (precisa puxar a imagem antes):
docker pull ghcr.io/mateuscasagra/open-to-work-backend:latest
docker run --rm ghcr.io/mateuscasagra/open-to-work-backend:latest php artisan key:generate --show
# Cole o output em APP_KEY=... no backend.env

# Senha forte do Postgres:
openssl rand -base64 24
# Cole em POSTGRES_PASSWORD (postgres.env) E em DB_PASSWORD (backend.env)

chmod 600 *.env
```

> **Pra primeira subida**: pode deixar `RESEND_KEY` vazio e setar `AUTH_EMAIL_VERIFICATION=false`. OAuth também pode ficar vazio. Habilite depois.

## 5. SSL com Let's Encrypt (certbot)

Trick: certbot precisa de HTTP rodando, mas nginx precisa do cert pra subir HTTPS. Resolva temporariamente:

```bash
cd /opt/opentowork

# 5.1 Comente as linhas SSL e o bloco 443 no nginx/default.conf temporariamente
# (ou use o trick com server HTTP-only inicial)

# 5.2 Subir só nginx + backend + frontend pra obter o cert:
docker compose -f docker-compose.prod.yml up -d nginx backend frontend postgres redis

# 5.3 Rodar certbot via container (não precisa instalar no host):
docker run --rm \
  -v /opt/opentowork/nginx/ssl:/etc/letsencrypt \
  -v /opt/opentowork/nginx/certbot:/var/www/certbot \
  -p 80:80 \
  certbot/certbot certonly --standalone \
    -d opentowork.app.br -d www.opentowork.app.br \
    --email seu@email.com --agree-tos --no-eff-email
# (pare o nginx antes: docker compose stop nginx)

# 5.4 Renovação automática via cron:
echo '0 3 * * * docker run --rm -v /opt/opentowork/nginx/ssl:/etc/letsencrypt -v /opt/opentowork/nginx/certbot:/var/www/certbot certbot/certbot renew --quiet && docker compose -f /opt/opentowork/docker-compose.prod.yml exec nginx nginx -s reload' | crontab -
```

## 6. Subir tudo

```bash
cd /opt/opentowork
docker compose -f docker-compose.prod.yml up -d

# Rodar migrações:
docker compose -f docker-compose.prod.yml run --rm backend php artisan migrate --force

# Cachear config/routes/views:
docker compose -f docker-compose.prod.yml run --rm backend sh -c "\
  php artisan config:cache && \
  php artisan route:cache && \
  php artisan view:cache && \
  php artisan event:cache"

# Status:
docker compose -f docker-compose.prod.yml ps
docker compose -f docker-compose.prod.yml logs -f backend
```

Acesse https://opentowork.app.br — deve carregar a landing.

## 7. GitHub Actions (deploy automatizado)

Pra que `git push --tags` triggere deploy automático:

### 7.1 GitHub Environment

Repo → Settings → **Environments** → New environment: `production`.
Marque **Required reviewers** (você mesmo) pra exigir aprovação manual antes do deploy.

### 7.2 Secrets do repo

Repo → Settings → Secrets and variables → Actions → **New repository secret**:

| Nome | Valor |
|---|---|
| `PROD_HOST` | IP público da VPS |
| `PROD_USER` | `root` (ou user com sudo + acesso a /opt/opentowork) |
| `PROD_SSH_KEY` | Chave privada SSH (cole o conteúdo de `~/.ssh/id_ed25519`) |

> Se sua chave SSH não existe, gere: `ssh-keygen -t ed25519 -f ~/.ssh/otw_deploy` e adicione a `.pub` em `~/.ssh/authorized_keys` da VPS.

### 7.3 Trigger primeiro deploy

```bash
git tag v0.1.0
git push origin v0.1.0
```

Acompanhe em GitHub → Actions. Workflow:
1. CI roda (Pest + Pint + PHPStan + frontend lint/test/build)
2. Se passar, builda imagens e pusha pro GHCR
3. SSH na VPS e roda `/opt/opentowork/scripts/deploy.sh v0.1.0`

## 8. Backup (opcional, mas recomendado)

Backup diário do Postgres pra Cloudflare R2:

```bash
# Na VPS:
apt install -y awscli gpg
echo "<senha-forte-pra-criptografar>" > /root/.backup-key
chmod 600 /root/.backup-key

# Configurar awscli pra R2:
aws configure  # access key + secret do R2

# Cron diário às 3h:
echo "0 3 * * * R2_ENDPOINT=https://<accountid>.r2.cloudflarestorage.com /opt/opentowork/scripts/backup.sh >> /var/log/otw-backup.log 2>&1" | crontab -e
```

## Troubleshooting

| Problema | Solução |
|---|---|
| `502 Bad Gateway` | Backend não subiu. `docker compose logs backend` |
| OOM (Out of Memory) | Verifique swap (`free -h`); reduza `mem_limit` de algum serviço |
| Migrations falham | Postgres ainda não está healthy. Aguarde 10s e re-tente |
| Cert expirou | Re-rode comando do passo 5.3, depois `docker compose restart nginx` |
| GitHub Action falha no SSH | `PROD_SSH_KEY` precisa ser a chave PRIVADA inteira (incluindo `-----BEGIN/END-----`) |
| `permission denied` no GHCR pull | PAT da VPS expirou. Regere e refaça `docker login` |

## Estrutura esperada no VPS

```
/opt/opentowork/
├── docker-compose.prod.yml
├── nginx/
│   ├── default.conf
│   ├── ssl/                    # certificados Let's Encrypt
│   └── certbot/                # webroot pra renovação
├── scripts/
│   ├── deploy.sh
│   ├── backup.sh
│   └── restore.sh
└── secrets/
    ├── backend.env             # CONTÉM SECRETS
    └── postgres.env
```
