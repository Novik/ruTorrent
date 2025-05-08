############################################################
# Dockerfile to build ruTorrent and plugins
#
############################################################

ARG ARCH
ARG PHP_VER=8.3-apache
ARG RUTORRENT_VER=master

FROM ${ARCH}php:${PHP_VER} AS osbuild
ARG TARGETPLATFORM
ARG UID=1000
ARG GID=1000

ENV APP_HOME=/usr/src/app

RUN  sed -i 's/^Components: main$/& contrib non-free/' /etc/apt/sources.list.d/debian.sources \
         && apt update && apt install --no-install-recommends -y sudo gosu bash unzip g++ make file re2c autoconf openssl libssl-dev libevent-dev \
          rtorrent mediainfo \
          gzip p7zip p7zip-full p7zip-rar \
          ffmpeg sox python3-pip \
         && pip install --no-cache-dir --break-system-packages pipx \
         && pipx ensurepath

RUN  docker-php-ext-install sockets pcntl \
    && docker-php-source delete \
    && groupadd -g ${GID} runuser && useradd -u ${UID} -g ${GID} -m runuser \
    && usermod -a -G www-data runuser && usermod -a -G ${GID} www-data \
     && update-alternatives --install /usr/bin/python python /usr/bin/python3 1 \
     && pip  install --break-system-packages cloudscraper \
     && apt remove -y g++ make re2c autoconf libssl-dev libevent-dev \
     && apt autoremove -y --purge \
     && rm -rf /var/www/html \
#    && ln -s $APP_HOME /var/www/html \
    && ln -s /etc/apache2/mods-available/rewrite.load /etc/apache2/mods-enabled/rewrite.load \
    && ln -s /etc/apache2/mods-available/proxy.load /etc/apache2/mods-enabled/proxy.load \
    && ln -s /etc/apache2/mods-available/remoteip.load /etc/apache2/mods-enabled/remoteip.load \
    && apt-get clean -y \
    && rm -rf /tmp/* \
    && rm -rf /var/lib/apt/lists/* \
    && rm -rf /usr/src/php.tar.xz

#COPY vhost.conf /etc/apache2/sites-enabled/000-default.conf

RUN echo "${TARGETPLATFORM}" | grep -q "arm" \
           || {  \
            BUILDPLATFORM_SHORT=$(echo "${TARGETPLATFORM}" | grep -q "64" && echo "amd64" || echo "x32"); \
            curl -L "https://github.com/TheGoblinHero/dumptorrent/releases/download/v1.3/dumptorrent_linux_$BUILDPLATFORM_SHORT.tar.gz" \
                | tar -xzf - -C "/usr/bin"; \
     } && exit 0


FROM ${ARCH}unzel/rutorrent:builder-base AS rutorrent_src
LABEL org.opencontainers.image.authors="hwk <nelu@github.com>"

ARG STORAGE_DIR=/data
ARG UID=1000
ARG GID=1000

ENV PATH="$PATH:$APP_HOME"

WORKDIR $APP_HOME

COPY --chown=www-data:www-data ./ $APP_HOME

RUN sh -c "cat <<EOF > /home/runuser/.rtorrent.rc \
directory = ${STORAGE_DIR}/downloads \
session = ${STORAGE_DIR}/.session \
port_range = 55951-55952 \
port_random = no \
check_hash = yes \
encryption = allow_incoming,enable_retry,prefer_plaintext \
scgi_port = 0.0.0.0:5000 \
EOF" \
    && mkdir -p "$STORAGE_DIR/downloads" "$STORAGE_DIR/.session" \
    && chown -R ${UID}:${GID} "$STORAGE_DIR" /home/runuser/.rtorrent.rc \
    && chmod 775 -R "$APP_HOME" \
    && chown -R www-data:www-data "$APP_HOME" \
    && rm -rf /var/www/html && ln -s "$APP_HOME"  "/var/www/html"

STOPSIGNAL TERM

VOLUME ["$STORAGE_DIR", "/var/www/html"]
