FROM php:8.3-cli

COPY movdl /usr/local/bin/movdl

RUN chmod +x /usr/local/bin/movdl

ENTRYPOINT ["movdl"]

