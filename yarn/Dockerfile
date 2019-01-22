FROM node
ADD . /app
WORKDIR /app
RUN yarn install && yarn encore dev
CMD ["yarn", "encore", "dev", "--watch"]