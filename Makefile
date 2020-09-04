.PHONY: check
check: test analyse


.PHONY: test
test:
	./bin/easytest


.PHONY: analyse
analyse:
	./phpstan.phar analyse


.PHONY: baseline
baseline:
	./phpstan.phar analyse --generate-baseline


.PHONY: build
build:
	./bin/makephar
