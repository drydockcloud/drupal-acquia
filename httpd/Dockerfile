FROM httpd:2.4.18

RUN apt-get update && \
    apt-get install libonig-dev=6.9.6 && \
    apt-get install -y --no-install-recommends openssl=1.0.1t-1+deb8u12 && \
    openssl req -x509 -out /usr/local/apache2/conf/server.crt \
      -keyout /usr/local/apache2/conf/server.key \
      -newkey rsa:2048 -nodes -sha256 -subj '/CN=localhost' && \
    apt-get remove -y openssl && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

COPY ./httpd.conf /usr/local/apache2/conf/httpd.conf
