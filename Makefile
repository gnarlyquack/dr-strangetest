.PHONY: test
test:
	./bin/easytest
	./phpstan.phar analyse src bin


.PHONY: build
build:
	./bin/makephar
