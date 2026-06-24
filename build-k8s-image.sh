#! /bin/sh

if [[ "$(docker images -q firehub-base-image 2> /dev/null)" == "" ]]; then
  echo "Build base image";
  docker buildx build --platform linux/amd64 -t firehub-base-image -f ./k8s/base.Dockerfile .;
fi

echo "Building app image";
docker buildx build --platform linux/amd64 -t firehub-deploy -f ./k8s/php.fpm.Dockerfile .;