.PHONY: explain docker-build-base docker-build-base-php8 docker-build-server docker-build-client docker-build docker-build-php8 \
		dev dev8 dev-clean dev-fresh dev8-fresh exec-server exec-client exec-redis lint lint-dry test test-coverage

ROOT_DIR := $(shell dirname $(realpath $(firstword $(MAKEFILE_LIST))))

explain:
	@echo "For quick start, run 'make dev'"

docker-build-base:
	@echo "Building base image..."
	docker build -t tus-php-base -f $(ROOT_DIR)/docker/base/Dockerfile $(ROOT_DIR)/docker/base/

docker-build-base-php8:
	@echo "Building base image for php8..."
	docker build -t tus-php-base -f $(ROOT_DIR)/docker/base/Dockerfile.php8 $(ROOT_DIR)/docker/base/

docker-build-server:
	@echo "Building server image..."
	docker build -t tus-php-server -f $(ROOT_DIR)/docker/server/Dockerfile $(ROOT_DIR)/docker/server/

docker-build-client:
	@echo "Building client image..."
	docker build -t tus-php-client -f $(ROOT_DIR)/docker/client/Dockerfile $(ROOT_DIR)/docker/client/

docker-build: docker-build-base docker-build-server docker-build-client

docker-build-php8: docker-build-base-php8 docker-build-server docker-build-client

dev:
	@$(ROOT_DIR)/bin/docker.sh

dev8:
	@PHP_VERSION=8 $(ROOT_DIR)/bin/docker.sh

dev-clean:
	@$(ROOT_DIR)/bin/clean.sh

dev-fresh: dev-clean dev

dev8-fresh: dev-clean dev8

exec-server:
	@docker exec -it tus-php-server sh

exec-client:
	@docker exec -it tus-php-client sh

exec-redis:
	@docker exec -it tus-php-redis bash -c "redis-cli"

clean:
	rm -rf composer.lock vendor/ coverage/ uploads/* .cache

vendor: composer.json $(wildcard composer.lock)
	@composer install

lint:
	@bin/lint.sh

lint-dry:
	@bin/lint.sh dry

test: vendor
	@composer test

test-coverage: vendor
	@composer test-coverage
