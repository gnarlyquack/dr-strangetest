.PHONY: check
check: analyse test


.PHONY: test
test:
	./bin/strangetest


.PHONY: analyse
analyse:
	./vendor/bin/phpstan analyse


.PHONY: baseline
baseline:
	./vendor/bin/phpstan analyse --generate-baseline


.PHONY: phar
phar:
	./bin/makephar


.PHONY: clean
clean:
	rm -r ./build
