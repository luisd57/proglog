# --- build Angular app ---
FROM node:24-alpine AS web-build
WORKDIR /web
COPY web/package*.json ./
RUN npm ci
COPY web/ .
RUN npm run build

# --- build NestJS app ---
FROM node:24-alpine AS api-build
WORKDIR /api
COPY api/package*.json ./
COPY api/prisma ./prisma
RUN npm ci
COPY api/ .
RUN npm run build

# --- runtime: one container serving SPA + API ---
FROM node:24-alpine
WORKDIR /app
ENV NODE_ENV=production
COPY api/package*.json ./
COPY api/prisma ./prisma
RUN npm ci --omit=dev && npx prisma generate
COPY --from=api-build /api/dist ./dist
COPY --from=web-build /web/dist/web/browser ./public
EXPOSE 3000
CMD ["sh", "-c", "npx prisma migrate deploy && node dist/main"]
