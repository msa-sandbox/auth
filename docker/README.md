# Auth Service - Source Image

This directory contains the Docker setup for building the **source image** of the Auth service.

The image is based on the shared PHP base image:
`alslob/msa-sandbox:php-base-8.2`

---

## Build

```bash
  cd ./../
  docker build -t alslob/msa-sandbox:auth-source-latest -f docker/Dockerfile .
```

## Push to Docker Hub

```bash
  docker push alslob/msa-sandbox:auth-source-latest
```

## Notes

 - PHP and Composer are included in the base image — no local PHP installation required.
 - vendor/ and other build artifacts are excluded via .dockerignore.
 - Use this image in Kubernetes deployments as the service runtime.
