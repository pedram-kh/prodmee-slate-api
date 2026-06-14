# Deploying Prodmee Slate to AWS

Target architecture:

```
Browser → CloudFront → S3 (Vue SPA)
        → ALB (HTTPS) → ECS Fargate (Laravel API) → RDS Postgres
                                                   → S3 (files, presigned)
                                                   → Anthropic (Sicala)
                                                   → SES (OTP/invite email)
Secrets: AWS Secrets Manager   DNS/TLS: Route 53 + ACM
```

Two repos deploy independently:
- `prodmee-slate-api` → Docker image on ECS Fargate.
- `prodmee-slate-web` → static build synced to S3 + CloudFront.

## 1. Provision infrastructure (Terraform)
All AWS resources are defined in `infra/`.

```bash
cd infra
cp terraform.tfvars.example terraform.tfvars   # edit domains, db password, app_key
terraform init
terraform apply
```

Generate `app_key` with `php artisan key:generate --show`. Terraform creates:
ECR, ECS cluster/service/task, ALB + HTTPS listener, RDS Postgres, the files
bucket (private, CORS) and SPA bucket + CloudFront (OAC), Secrets Manager,
SES domain identity + DKIM, and Route 53 + ACM certs for both hostnames.

Note the outputs: `ecr_repository_url`, `ecs_cluster`, `ecs_service`,
`spa_bucket`, `cloudfront_distribution_id`, `api_url`, `app_url`.

> SES starts in sandbox mode — request production access (and verify the domain)
> before sending to arbitrary recipients.

## 2. First image push
The ECS task references `:latest`, so push an initial image before the service
can become healthy:

```bash
aws ecr get-login-password --region <region> | docker login --username AWS --password-stdin <ecr_repository_url>
docker build -t <ecr_repository_url>:latest .
docker push <ecr_repository_url>:latest
```

Migrations run automatically on container boot (`docker/entrypoint.sh`,
`RUN_MIGRATIONS=true`). Disable by setting `RUN_MIGRATIONS=false` and running a
one-off task instead.

## 3. CI/CD (GitHub Actions)
Both repos assume an IAM role via OIDC. Create a deploy role trusted by GitHub
and set:

API repo — secrets: `AWS_DEPLOY_ROLE_ARN`; variables: `AWS_REGION`,
`ECR_REPOSITORY`, `ECS_CLUSTER`, `ECS_SERVICE`.
→ `.github/workflows/ci.yml` runs tests; `deploy.yml` builds/pushes the image and
forces a new ECS deployment on pushes to `main`.

Web repo — secrets: `AWS_DEPLOY_ROLE_ARN`; variables: `AWS_REGION`, `SPA_BUCKET`,
`CLOUDFRONT_DISTRIBUTION_ID`, `VITE_API_BASE` (e.g. `https://api.slate.prodmee.app`).
→ `.github/workflows/deploy.yml` builds the SPA, syncs to S3, invalidates CloudFront.

## 4. Configuration & secrets
Runtime env is injected by the ECS task definition; sensitive values
(`APP_KEY`, `DB_PASSWORD`, `ANTHROPIC_API_KEY`) come from the
`prodmee-slate/app` secret in Secrets Manager. The Anthropic key can also be set
later by an admin in **Settings → API Key** (stored encrypted in `app_settings`,
which takes precedence over the env value).

## 5. Post-deploy checklist
- [ ] `terraform apply` clean; DNS resolves for both hostnames.
- [ ] Initial image pushed; ECS service stable; `/up` healthy behind the ALB.
- [ ] SES out of sandbox; OTP email delivers.
- [ ] Seed or invite the first admin user.
- [ ] SPA build deployed; CloudFront serves `index.html` for SPA routes.
- [ ] Set the Anthropic key in Settings; "Test connection" passes.
```
