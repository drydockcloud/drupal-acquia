FROM alpine:3.10.1

COPY getconfig.sh scripts/
RUN curl -Lso scripts/getconfig.php https://raw.githubusercontent.com/drydockcloud/tools-php-getconfig/master/getconfig.php

ENTRYPOINT [ "scripts/getconfig.sh" ]