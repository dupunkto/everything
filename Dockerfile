FROM oven/bun:alpine

RUN apk add --no-cache curl

WORKDIR /usr/src/app

ENV DIST=dist
ENV PUBLIC=public
ENV VENDOR=public/vendor

COPY package.json bun.lockb .
RUN bun install --frozen-lockfile
RUN mkdir -p $VENDOR
RUN curl -sLo $VENDOR/reset.css https://cdn.dupunkto.org/reset.css

COPY . .

RUN sed -E 's|\./public/|\./dist/|' linio.ts > linio.ts.tmp && mv -f linio.ts.tmp linio.ts
RUN bun run build.ts
RUN bun build --define DEV=false --define PORT=9000 --compile linio.ts --outfile a.out
RUN cp a.out /usr/local/bin/linio

EXPOSE 9000/tcp
USER bun

CMD [ "linio", "--hostname", "0.0.0.0", "/linio" ]
