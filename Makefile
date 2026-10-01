.DEFAULT_GOAL := help
PYTHON ?= python3
PHP ?= php
NODE ?= node
PORT ?= 8091
LOCAL_CONSENT_WP ?= $(CURDIR)/.local/wordpress
export LOCAL_CONSENT_WP

.PHONY: help setup dev seed lint test test-js test-php test-package package
help:
	@printf '%s\n' 'make dev          Lokales WordPress starten (http://127.0.0.1:8091)' 'make setup        Lokales WordPress mit SQLite einrichten' 'make seed         Lokale Testseite wiederherstellen' 'make test         Syntax, JavaScript, PHP und Paket prüfen' 'make test-js      JavaScript-Tests ohne WordPress' 'make package      Installierbares ZIP in dist/ bauen' 'Variablen: PORT, LOCAL_CONSENT_WP, WORDPRESS_VERSION, WP_CLI_PHAR'
setup:
	$(PYTHON) scripts/dev.py setup --port $(PORT)
dev: setup
	$(PYTHON) scripts/dev.py serve --port $(PORT)
seed:
	$(PYTHON) scripts/dev.py seed
lint:
	@for file in local-consent.php uninstall.php includes/*.php scripts/*.php; do $(PHP) -l "$$file" || exit; done
	@for file in assets/*.js; do $(NODE) --check "$$file" || exit; done
test: lint test-js test-php test-package
test-js:
	$(NODE) --test tests/*.test.cjs
test-php:
	$(PHP) tests/html.test.php
	$(PHP) tests/design.test.php
test-package:
	$(PYTHON) -m unittest discover -s tests -p 'test_*.py' -v
package:
	$(PYTHON) scripts/package.py $(if $(TAG),--tag $(TAG),)
