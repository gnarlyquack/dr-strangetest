.PHONY: check
check: test analyse


.PHONY: test
test:
	./bin/easytest


.PHONY: analyse
analyse:
	./vendor/bin/phpstan analyse


.PHONY: baseline
baseline:
	./vendor/bin/phpstan analyse --generate-baseline


.PHONY: build
build:
	./bin/makephar
