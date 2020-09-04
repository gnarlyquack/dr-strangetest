.PHONY: check
check: test analyse


.PHONY: test
test:
	./bin/easytest


.PHONY: analyse
analyse:
	./phpstan.phar analyse


.PHONY: build
build:
	./bin/makephar
