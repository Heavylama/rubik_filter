FROM atmoz/sftp

RUN apt-get update && \
apt-get install -y procmail fetchmail mutt nano

COPY sshd_config /etc/ssh/sshd_config