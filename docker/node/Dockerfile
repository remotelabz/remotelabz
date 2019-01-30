FROM node
ARG userid

RUN useradd ${user} --uid ${userid} 

USER ${user}
WORKDIR /app
ENTRYPOINT [ "yarn" ]
CMD [ "install" ]