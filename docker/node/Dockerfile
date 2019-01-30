FROM node
ARG userid

RUN usermod -u ${userid} ${user}

USER ${user}
WORKDIR /app
ENTRYPOINT [ "yarn" ]
CMD [ "install" ]