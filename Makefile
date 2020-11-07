.PHONY: docker-build-base docker-build dev dev-clean dev-fresh exec-server exec-client exec-redis deps lint test test-coverage

ROOT_DIR := $(shell dirname $(realpath $(firstword $(MAKEFILE_LIST))))

explain:
	@echo "For quick start, run 'make dev'"

docker-build-base:
	@echo "Building base image..."
	docker build -t tus-php-base -f $(ROOT_DIR)/docker/base/Dockerfile $(ROOT_DIR)/docker/base/

docker-build: docker-build-base
	@echo "Building server image..."
	docker build -t tus-php-server -f $(ROOT_DIR)/docker/server/Dockerfile $(ROOT_DIR)/docker/server/

	@echo "Building client image..."
	docker build -t tus-php-client -f $(ROOT_DIR)/docker/client/Dockerfile $(ROOT_DIR)/docker/client/

dev:
	@$(ROOT_DIR)/bin/docker.sh

dev-clean:
	@$(ROOT_DIR)/bin/clean.sh

dev-fresh: dev-clean dev

exec-server:
	@docker exec -it tus-php-server sh

exec-client:
	@docker exec -it tus-php-client sh

exec-redis:
	@docker exec -it tus-php-redis bash -c "redis-cli"

clean:
	rm -rf composer.lock vendor/ coverage/ uploads/* .cache

deps:
	@composer install

lint:
	@composer lint

test:
	@composer test

test-coverage:
	@composer test-coverage
