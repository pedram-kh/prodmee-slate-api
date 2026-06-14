terraform {
  required_version = ">= 1.6"
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }
  # Configure a remote backend (S3 + DynamoDB lock) in real use:
  # backend "s3" { bucket = "prodmee-tf-state" key = "slate/terraform.tfstate" region = "eu-west-1" }
}

provider "aws" {
  region = var.region
}

# CloudFront + its ACM certificate must live in us-east-1.
provider "aws" {
  alias  = "us_east_1"
  region = "us-east-1"
}

data "aws_caller_identity" "current" {}

# Use the account's default VPC/subnets to keep this skeleton self-contained.
# For production, replace with a dedicated VPC module.
data "aws_vpc" "default" {
  default = true
}

data "aws_subnets" "default" {
  filter {
    name   = "vpc-id"
    values = [data.aws_vpc.default.id]
  }
}
