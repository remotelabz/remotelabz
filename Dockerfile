FROM busybox:latest

COPY . /app
RUN mkdir -p /app/vendor && \
    mkdir -p /app/var && \
    mkdir -p /.composer && \
    chown 33:33 -R /.composer && \
    chown 33:33 -R /app/var && \
    chown 33:33 -R /app/vendor && \
    chown 33:33 /app/.env