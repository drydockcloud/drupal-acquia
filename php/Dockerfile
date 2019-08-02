FROM ubuntu:16.04

# NOTE: This is not a production build: for sandbox/CI use only

# Pass the version of PHP to use as a build arg - must have corresponding config file
ARG version=7.2

# Build dependencies - removed after build is complete
ENV BUILDDEPS autoconf \
        dpkg-dev \
        freetds-dev \
        g++ \
        gcc \
        libbz2-dev \
        libc-client2007e-dev \
        libc-dev \
        libcurl4-openssl-dev \
        libdb-dev \
        libedit-dev \
        libfreetype6-dev \
        libgdbm-dev \
        libgmp-dev \
        libgpgme11-dev \
        libjpeg-dev \
        libkrb5-dev \
        libldap2-dev \
        libmagickcore-dev \
        libmagickwand-dev \
        libmcrypt-dev \
        libmemcached-dev \
        libmysqlclient-dev \
        libpng-dev \
        libpq-dev \
        libpspell-dev \
        libreadline-dev \
        libsasl2-dev \
        libsqlite3-dev \
        libssh2-1-dev \
        libssl-dev \
        libsslcommon2-dev \
        libtidy-dev \
        libxml2-dev \
        libxpm-dev \
        libxslt-dev \
        libyaml-dev \
        libzip-dev \
        make \
        pkg-config \
        re2c \
        zlib1g-dev

# We use bash process substitution to simplify the validation diff commands
SHELL ["/bin/bash", "-o", "pipefail", "-c"]

# Set the path to include the build PHP bin directory
ENV PATH=$PATH:/usr/local/php/bin

# Copy in the config file specified by the "version" build arg
COPY "${version}"-config /usr/src/config

# Install prerequisites, build PHP & extensions, validate the build then clean up
# Ignore some linting rules that interfere with build/validation (cd, php vars etc)
# hadolint ignore=SC1091,SC2016,SC2086,DL3003,DL3008
RUN . /usr/src/config && \
        apt-get update && apt-get install -y \
        ${BUILDDEPS} \
        # Permanently installed dependencies
        ca-certificates \
        curl \
        file \
        lsb-release \
        wget \
        xz-utils \
        --no-install-recommends && rm -r /var/lib/apt/lists/* && \
        \
        # Download and build php
        cd /usr/src && \
        curl --silent --location --output php.tar.xz "http://us1.php.net/get/php-${EXACT_VERSION}.tar.xz/from/this/mirror" && \
        tar xf php.tar.xz && \
        cd /usr/src/php-* && \
        # Workaround for library issue on Ubuntu 16.04
        ln -s /usr/lib/libc-client.a /usr/lib/x86_64-linux-gnu/libc-client.a && \
        # Build CLI - variable not quoted as it contains arguments that are separate words
        ./configure ${CLI_CONFIGURE_OPTIONS} && \
        make -j "$(nproc)" && \
        make install && \
        # Build FPM - variable not quoted as it contains arguments that are separate words
        ./configure ${FPM_CONFIGURE_OPTIONS} && \
        make -j "$(nproc)" && \
        make install && \
        \
        # Copy the etc configuration into place, replacing all existing config.
        mkdir -p "/usr/local/php/etc/conf.d/" "/usr/local/php/etc/cli/conf.d/" "/usr/local/php/etc/fpm/conf.d/" && \
        echo "${CLI_INI}" > "${CLI_INI_FILE}" && \
        echo "${FPM_INI}" > "${FPM_INI_FILE}" && \
        \
        # Install PHP extensions
        pecl channel-update pecl.php.net; \
        # Install tideways from source (not available from pecl)
        cd /usr/src && \
        curl --silent --location --output xhprof.tar.gz https://github.com/tideways/php-xhprof-extension/archive/v4.1.5.tar.gz && \
        tar xzf xhprof.tar.gz && \
        cd php-xhprof-extension-* && \
        phpize && \
        ./configure && \
        make && \
        make install  &&\
        # Install, including ssh2 (which is installed but not via PECL)
        yes '' | pecl install -n ${PECL} ssh2-1.1.2; \
        # Move the extensions from the extension API version directory to the /opt/php/lib directory to simplify configuration (PECL ext_dir does not work)
        mv /usr/local/php/lib/php/extensions/no-debug-non-zts-[0-9]*/* /usr/local/php/lib/php/extensions && \
        # Repeat the install/move so that extensions with dependencies can install
        yes '' | pecl install -n ${PECL}; \
        mv /usr/local/php/lib/php/extensions/no-debug-non-zts-[0-9]*/* /usr/local/php/lib/php/extensions && \
        # Configure php-fpm to use an existant user
        sed -i'' -e 's/^group = nobody/group = nogroup/' /usr/local/php/etc/php-fpm.d/www.conf.default && \
        \
        # Clean up
        rm -rf /usr/src/* && \
        apt-get purge -y ${BUILDDEPS} && \
        \
        # Validate that distro, loaded extensions and config match
        if ! diff -uw <(lsb_release -ir) <(echo "${DISTRO}"); then echo "Distro does not match expected"; exit 1; fi && \
        if ! diff -uw <(php -r '$e = get_loaded_extensions(); sort($e); print(implode(PHP_EOL, $e));') <(echo "${EXTENSIONS}"); then echo "PHP extensions do not match expected"; exit 2; fi && \
        if ! diff -uw <(php -r 'foreach (ini_get_all() as $k => $v) { print "$k: $v['global_value']\n"; }') <(echo "${CONFIG}"); then echo "PHP config does not match expected"; exit 3; fi

WORKDIR /usr/local/php/etc
# Configure php-fpm for Docker: the below is sourced from https://github.com/docker-library/php/blob/master/7.1/stretch/fpm/Dockerfile#L198
RUN if [ -d php-fpm.d ]; then \
        # for some reason, upstream's php-fpm.conf.default has "include=NONE/etc/php-fpm.d/*.conf"
        sed 's!=NONE/!=!g' php-fpm.conf.default | tee php-fpm.conf > /dev/null; \
        cp php-fpm.d/www.conf.default php-fpm.d/www.conf; \
        else \
        # PHP 5.x doesn't use "include=" by default, so we'll create our own simple config that mimics PHP 7+ for consistency
        mkdir php-fpm.d; \
        cp php-fpm.conf.default php-fpm.d/www.conf; \
        { \
        echo '[global]'; \
        echo 'include=etc/php-fpm.d/*.conf'; \
        } | tee php-fpm.conf; \
        fi \
        && { \
        echo '[global]'; \
        echo 'error_log = /proc/self/fd/2'; \
        echo; \
        echo '[www]'; \
        echo '; if we send this to /proc/self/fd/1, it never appears'; \
        echo 'access.log = /proc/self/fd/2'; \
        echo; \
        echo 'clear_env = no'; \
        echo; \
        echo '; Ensure worker stdout and stderr are sent to the main error log.'; \
        echo 'catch_workers_output = yes'; \
        } | tee php-fpm.d/docker.conf \
        && { \
        echo '[global]'; \
        echo 'daemonize = no'; \
        echo; \
        echo '[www]'; \
        echo 'listen = 9000'; \
        } | tee php-fpm.d/zz-docker.conf

WORKDIR /var/www
CMD ["/usr/local/php/sbin/php-fpm"]