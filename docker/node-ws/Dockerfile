FROM node:20-bookworm-slim AS build

WORKDIR /app

COPY ws-server/package.json ws-server/package-lock.json ./
RUN npm ci

COPY ws-server/tsconfig.json ./
COPY ws-server/src ./src
RUN npm run build

FROM node:20-bookworm-slim

RUN apt-get update && apt-get install -y --no-install-recommends \
        bash \
        curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY ws-server/package.json ws-server/package-lock.json ./
RUN npm ci --omit=dev

COPY --from=build /app/dist ./dist

EXPOSE 6001

CMD ["node", "dist/index.js"]
