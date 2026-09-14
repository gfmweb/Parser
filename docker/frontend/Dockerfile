FROM node:20-bookworm-slim AS base

RUN apt-get update && apt-get install -y --no-install-recommends bash \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

FROM base AS dev
WORKDIR /app
EXPOSE 5173
CMD ["sh", "-c", "npm install && npm run dev -- --host 0.0.0.0 --port 5173"]

FROM base AS build
COPY frontend/package.json frontend/package-lock.json* ./
RUN npm install
COPY frontend/ ./
RUN npm run build

FROM nginx:1.27-alpine AS production
COPY --from=build /app/dist /usr/share/nginx/html
