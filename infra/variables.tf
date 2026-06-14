variable "region" {
  type    = string
  default = "eu-west-1"
}

variable "project" {
  type    = string
  default = "prodmee-slate"
}

variable "root_domain" {
  type        = string
  description = "Hosted zone domain, e.g. prodmee.app"
}

variable "app_domain" {
  type        = string
  description = "SPA hostname, e.g. slate.prodmee.app"
}

variable "api_domain" {
  type        = string
  description = "API hostname, e.g. api.slate.prodmee.app"
}

variable "db_username" {
  type    = string
  default = "prodmee"
}

variable "db_password" {
  type      = string
  sensitive = true
}

variable "app_key" {
  type        = string
  sensitive   = true
  description = "Laravel APP_KEY (base64:...)"
}

variable "anthropic_api_key" {
  type      = string
  sensitive = true
  default   = ""
}

variable "container_cpu" {
  type    = number
  default = 512
}

variable "container_memory" {
  type    = number
  default = 1024
}

variable "desired_count" {
  type    = number
  default = 2
}
