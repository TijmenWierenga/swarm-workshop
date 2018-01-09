FROM node:6-alpine

ENV NPM_CONFIG_PREFIX=/home/node/.npm-global
ENV NODE_ENV=dev

RUN deluser --remove-home node \
    && addgroup -S node && adduser -S -G node node

COPY --chown=node build /home/node/app/build
COPY --chown=node config /home/node/app/config
COPY --chown=node src /home/node/app/src
COPY --chown=node static /home/node/app/static
COPY --chown=node test /home/node/app/test
COPY --chown=node .babelrc .editorconfig .eslintignore .eslintrc.js .postcssrc.js index.html package.json yarn.lock /home/node/app/

WORKDIR /home/node/app
RUN npm install

EXPOSE 8080

CMD ["npm", "run", "dev"]