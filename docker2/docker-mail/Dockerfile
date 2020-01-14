FROM tvial/docker-mailserver:latest

RUN apt-get update && apt-get install procmail

COPY ./procmail-config.cf /etc/postfix/procmail-config.cf
RUN  cat /etc/postfix/procmail-config.cf >> /etc/postfix/main.cf && rm /etc/postfix/procmail-config.cf

COPY ./procmail /etc/procmail

RUN groupadd -g 500 ldap_users && useradd -u 1001 -g 500 -m adam && echo 'adam:adam' | chpasswd
