#!/usr/bin/make

SHELL = /bin/bash

.PHONY: help setup up test down hard-down
.DEFAULT_GOAL: help

# https://marmelab.com/blog/2016/02/29/auto-documented-makefile.html
help: ## Show this help
	@printf "\033[33m%s:\033[0m\n" 'Available commands'
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z0-9_-]+:.*?## / {printf "  \033[32m%-18s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

setup: ## Setup project
	composer min-setup
	./vendor/bin/sail up -d

up: ## Up containers
	./vendor/bin/sail up -d --remove-orphans

test: ## Run tests
	./vendor/bin/sail test

down: ## Down containers
	./vendor/bin/sail down

hard-down: ## Down containers and remove volumes
	./vendor/bin/sail down -v
