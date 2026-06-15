# Deployment Guide — Prodmee Slate API

This is the authoritative guide for deploying the **backend** (`prodmee-slate-api`)
to AWS. Deploy this repo **first** — its Terraform creates the shared S3 bucket and
CloudFront distribution that the web repo (`prodmee-slate-web`) depends on.

## Architecture

```
Browser → CloudFront → S3 (Vue SPA)
        → ALB (HTTPS) → ECS Fargate (Laravel API) → RDS Postgres
                                                   → S3 (files, presigned)
                                                   → Anthropic (Sicala)
                                                   → SES (OTP/invite email)
Secrets: AWS Secrets Manager   DNS/TLS: Route 53 + ACM
```

The two repos deploy independently:
- `prodmee-slate-api` → Docker image on ECS Fargate (this guide).
- `prodmee-slate-web` → static build synced to S3 + CloudFront (see that repo's `deployment-guide.md`).

## Prerequisites

You need these before starting:

| Tool / asset | Why |
| --- | --- |
| AWS account with admin (or equivalent) access | Terraform provisions IAM, ECS, RDS, S3, etc. |
| A domain in a **Route 53 hosted zone** | ACM certs + DNS records are created for it |
| [Terraform](https://developer.hashicorp.com/terraform/downloads) ≥ 1.5 | Provision infrastructure |
| Docker | Build/push the API image |
| AWS CLI v2 (configured: `aws configure`) | Push images, force ECS deploys |
| PHP 8.4 (local) | Generate `APP_KEY` via `php artisan key:generate --show` |
| GitHub CLI (`gh`) — optional | Set repo secrets/variables for CI/CD |

## What you must SET

### 1. `infra/terraform.tfvars`

Copy the example and fill it in (`cd infra && cp terraform.tfvars.example terraform.tfvars`):

| Variable | Required | Default | Notes |
| --- | --- | --- | --- |
| `region` | – | `eu-west-1` | AWS region for all resources |
| `project` | – | `prodmee-slate` | Name prefix for resources |
| `root_domain` | **yes** | – | Hosted zone domain, e.g. `prodmee.app` |
| `app_domain` | **yes** | – | SPA hostname, e.g. `slate.prodmee.app` |
| `api_domain` | **yes** | – | API hostname, e.g. `api.slate.prodmee.app` |
| `db_username` | – | `prodmee` | RDS master username |
| `db_password` | **yes** | – | Strong RDS password (sensitive) |
| `app_key` | **yes** | – | `base64:…` from `php artisan key:generate --show` |
| `anthropic_api_key` | no | `""` | Optional; admins can instead set it in **Settings → API Key** |
| `container_cpu` | – | `512` | Fargate task CPU units |
| `container_memory` | – | `1024` | Fargate task memory (MiB) |
| `desired_count` | – | `2` | Number of API tasks |

### 2. GitHub Actions — secrets & variables (for CI/CD)

CI/CD assumes an IAM role via **OIDC**. Create a deploy role trusted by GitHub, then set on **this** repo:

| Kind | Name | Example | Used by |
| --- | --- | --- | --- |
| Secret | `AWS_DEPLOY_ROLE_ARN` | `arn:aws:iam::123…:role/gh-deploy` | OIDC assume-role |
| Variable | `AWS_REGION` | `eu-west-1` | all AWS calls |
| Variable | `ECR_REPOSITORY` | `prodmee-slate` | image push target |
| Variable | `ECS_CLUSTER` | `prodmee-slate` | force-new-deployment |
| Variable | `ECS_SERVICE` | `prodmee-slate` | force-new-deployment |

(`ECR_REPOSITORY`, `ECS_CLUSTER`, `ECS_SERVICE` all default to the `project` value, `prodmee-slate`.)

### 3. Secrets Manager (created by Terraform, values from tfvars)

Terraform creates a `${project}/app` secret (e.g. `prodmee-slate/app`) and injects these into the
container as env vars. You don't set them by hand — they come from your tfvars:

`APP_KEY`, `DB_PASSWORD`, `ANTHROPIC_API_KEY`.

## What you must KNOW

- **SES starts in sandbox mode.** You can only email verified addresses until you
  request production access. DKIM records are created automatically, but request
  the sandbox exit before real users sign in (OTP + invites go through SES).
- **`MAIL_FROM_ADDRESS`** must be on the verified `root_domain` (defaults to
  `no-reply@prodmee.app`). Override via the task env if your domain differs.
- **The ECS task references the `:latest` image.** You must push an initial image
  before the service can become healthy (see step 2 below).
- **Migrations run automatically on container boot** (`docker/entrypoint.sh`,
  `RUN_MIGRATIONS=true`). Set `RUN_MIGRATIONS=false` to disable and run a one-off task instead.
- **Health check:** the ALB checks `/up`. The service won't stabilize until it returns 200.
- **Logs:** the app logs to stderr → CloudWatch group `/ecs/prodmee-slate`
  (30-day retention). Stream with `aws logs tail /ecs/prodmee-slate --follow`.
- **The Anthropic key has two sources:** the env value (from Secrets Manager) is a
  fallback; a key set by an admin in **Settings → API Key** is stored encrypted in
  `app_settings` and **takes precedence**.

## Deploy steps

### 1. Provision infrastructure

```bash
cd infra
cp terraform.tfvars.example terraform.tfvars   # edit per the table above
terraform init
terraform apply
```

Terraform creates: ECR, ECS cluster/service/task, ALB + HTTPS listener, RDS
Postgres, the files bucket (private, CORS) and SPA bucket + CloudFront (OAC),
Secrets Manager, SES domain identity + DKIM, and Route 53 + ACM certs for both hostnames.

Note the outputs — you'll need them for the web repo and the image push:
`ecr_repository_url`, `ecs_cluster`, `ecs_service`, `spa_bucket`,
`cloudfront_distribution_id`, `api_url`, `app_url`, `files_bucket`, `rds_endpoint`.

### 2. First image push

```bash
aws ecr get-login-password --region <region> | docker login --username AWS --password-stdin <ecr_repository_url>
docker build -t <ecr_repository_url>:latest .
docker push <ecr_repository_url>:latest
```

Then force the service to pull it (or wait for the next CI deploy):

```bash
aws ecs update-service --cluster <ecs_cluster> --service <ecs_service> --force-new-deployment --region <region>
```

### 3. CI/CD (ongoing)

With the secrets/variables above set, pushes to `main` trigger:
- `.github/workflows/ci.yml` — runs the test suite (SQLite).
- `.github/workflows/deploy.yml` — builds/pushes the image (tagged `:latest` and `:<sha>`),
  forces a new ECS deployment, and waits for the service to stabilize.

## Post-deploy checklist

- [ ] `terraform apply` clean; DNS resolves for both hostnames.
- [ ] Initial image pushed; ECS service stable; `/up` healthy behind the ALB.
- [ ] SES out of sandbox; OTP email delivers to a real address.
- [ ] Seed or invite the first admin user.
- [ ] Web repo deployed (its `deployment-guide.md`); CloudFront serves the SPA.
- [ ] Set the Anthropic key in **Settings → API Key**; "Test connection" passes.
- [ ] `aws logs tail /ecs/prodmee-slate --follow` shows app logs.
