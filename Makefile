# On Windows try https://frippery.org/busybox/

-include config.mk

version?=1.0.5
suffix?=
registry?=ghcr.io/amrc-factoryplus
repo?=acs-manager

tag=${registry}/${repo}:${version}${suffix}
build_args=

all: build push

.PHONY: all build push

build:
	docker build -t "${tag}" ${build_args} .

push:
	docker push "${tag}"

run:
	docker run -ti --rm "${tag}" /bin/sh

.PHONY: deploy restart logs

ifdef deployment

deploy: all restart logs

restart:
	kubectl rollout restart deploy/"${deployment}"
	kubectl rollout status deploy/"${deployment}"
	sleep 4

logs:
	kubectl logs -f deploy/"${deployment}"

else

deploy restart logs:
	: Set $${deployment} for automatic k8s deployment

endif
