output "ecr_repository_url" {
  value = aws_ecr_repository.api.repository_url
}

output "ecs_cluster" {
  value = aws_ecs_cluster.main.name
}

output "ecs_service" {
  value = aws_ecs_service.api.name
}

output "api_url" {
  value = "https://${var.api_domain}"
}

output "app_url" {
  value = "https://${var.app_domain}"
}

output "files_bucket" {
  value = aws_s3_bucket.files.bucket
}

output "spa_bucket" {
  value = aws_s3_bucket.spa.bucket
}

output "cloudfront_distribution_id" {
  value = aws_cloudfront_distribution.spa.id
}

output "rds_endpoint" {
  value = aws_db_instance.postgres.address
}
