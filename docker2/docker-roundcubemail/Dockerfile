FROM roundcube/roundcubemail:1.4.x-apache

RUN pecl install xdebug
RUN echo "zend_extension=$(find /usr/local/lib/php/extensions/ -name xdebug.so)" | tee /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.remote_enable=1" | tee -a /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.remote_handler=dbgp" | tee -a /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.remote_port=9000" | tee -a /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.remote_autostart=1" | tee -a /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.remote_connect_back=1" | tee -a /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.idekey=VSCODE" | tee -a /usr/local/etc/php/conf.d/xdebug.ini \
#    && echo "xdebug.remote_host=localhost" | tee -a /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.remote_log=/var/log/xdebug.log" | tee -a /usr/local/etc/php/conf.d/xdebug.ini

RUN apt-get update && apt-get install iputils-ping nano openssh-client -y
