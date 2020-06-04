FROM httpd:2.4.18

RUN apt-get update && apt-get install -y openssl && \
    openssl req -x509 -out /usr/local/apache2/conf/server.crt \
    -keyout /usr/local/apache2/conf/server.key \
    -newkey rsa:2048 -nodes -sha256 -subj '/CN=localhost'

COPY ./httpd.conf /usr/local/apache2/conf/httpd.conf