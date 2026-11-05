# syntax=docker/dockerfile:1
#
# Event booking site (booking-web/): React build served by nginx.
# Build context = this folder (Coolify "Base Directory" /booking-web). Docs: README.md.
#
#   docker build -t mawsim-booking-web booking-web
#   docker run --rm -p 18100:80 -e API_BASE_URL=https://mawsim.awj.om mawsim-booking-web
#   curl -i http://127.0.0.1:18100/healthz
#
# One image for every environment: the backend address is read at container
# start, not compiled in.
#   API_BASE_URL               required, backend without /api/v1 (https://mawsim.awj.om)
#   HOME_URL                   optional, where "/" (no event in the address) redirects
#   CONTENT_SECURITY_POLICY    optional, replaces the default CSP ("" = no CSP header)
#   PERMISSIONS_POLICY         optional, replaces the Permissions-Policy header

ARG NODE_IMAGE=node:22.22.3-alpine3.22
ARG NGINX_IMAGE=nginx:1.30.6-alpine3.24-slim

# ---------------------------------------------------------------- build
FROM ${NODE_IMAGE} AS build
WORKDIR /src
# Dependencies first (cached until package*.json change).
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund --loglevel=error
COPY . .
ARG SOURCE_COMMIT=unknown
RUN npm run build \
 # gzip_static: precompressed copies of the hashed assets (index.html is
 # rewritten at start and compressed on the fly).
 && find dist/assets -type f \( -name '*.js' -o -name '*.css' -o -name '*.svg' \) -size +1k -exec gzip -9 -k -n {} + \
 && printf '{"app":"booking-web","version":"%s","commit":"%s","built_at":"%s"}\n' \
      "$(node -p 'require("./package.json").version')" "$SOURCE_COMMIT" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" > dist/build-info.json \
 # The startup script needs its placeholders.
 && grep -q '__API_BASE_URL__' dist/index.html

# ---------------------------------------------------------------- runtime
FROM ${NGINX_IMAGE}
ENV API_BASE_URL="" \
    HOME_URL="" \
    PERMISSIONS_POLICY="camera=(), microphone=(), geolocation=(), payment=(), usb=()"
RUN rm -f /etc/nginx/conf.d/default.conf
COPY nginx/default.conf.template /etc/nginx/templates/default.conf.template
COPY --chmod=755 docker/15-booking-config.envsh /docker-entrypoint.d/15-booking-config.envsh
COPY --from=build /src/dist /usr/share/nginx/html
# Pristine copy: the startup script renders index.html from it on every start.
RUN cp /usr/share/nginx/html/index.html /usr/share/nginx/index.template.html
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
  CMD wget -q -O /dev/null http://127.0.0.1/healthz || exit 1
