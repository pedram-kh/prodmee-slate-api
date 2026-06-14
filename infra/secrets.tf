# App secrets are injected into the container from Secrets Manager (never baked
# into the image). ECS maps each JSON key to an env var via the task definition.
resource "aws_secretsmanager_secret" "app" {
  name = "${var.project}/app"
}

resource "aws_secretsmanager_secret_version" "app" {
  secret_id = aws_secretsmanager_secret.app.id
  secret_string = jsonencode({
    APP_KEY            = var.app_key
    DB_PASSWORD        = var.db_password
    ANTHROPIC_API_KEY  = var.anthropic_api_key
  })
}
