FROM prestashop/prestashop:9.0.x-apache

ENV PS_DEV_MODE 1
ENV PS_INSTALL_AUTO 1
ENV PS_HANDLE_DYNAMIC_DOMAIN 1
ENV PS_COUNTRY LV
ENV ADMIN_PASSWD admin

RUN apt-get update
RUN apt-get install -y --no-install-recommends unzip wget
