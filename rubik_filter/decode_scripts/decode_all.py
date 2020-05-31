#!/usr/bin/python3

import sys
import email
from email import policy

DECODE_HEADER = ("-h" in sys.argv)
DECODE_BODY = ("-b" in sys.argv)

if (not DECODE_HEADER and not DECODE_BODY):
    DECODE_BODY = True
    DECODE_HEADER = True

mail = email.message_from_binary_file(
    open('test_iso.eml', 'rb'), policy=policy.default)


def decode_headers(mail):
    for header, value in mail.items():
        print(header+": ", end='', flush=True)
        for text, encoding in email.header.decode_header(value):
            if encoding is None:
                encoding = 'utf-8'
            if not isinstance(text, str):
                text = str(text, encoding)
            text += "\n"
            sys.stdout.buffer.write(text.encode('utf-8'))
        print()


def decode_body(mail):
    body = mail.get_body()
    payload = body.get_content()
    sys.stdout.buffer.write(payload.encode('utf-8'))
    print()


if (DECODE_HEADER):
    decode_headers(mail)
if (DECODE_BODY):
    decode_body(mail)
