import sys
import email
from email import policy

for header, value in email.message_from_binary_file(sys.stdin.buffer, policy=policy.default).items():
    print(header+': ', end='', flush=True)
    for text, encoding in email.header.decode_header(value):
        if encoding is None:
            encoding = 'utf-8'
        if not isinstance(text, str):
            text = str(text, encoding)
        text += '\r\n'
        sys.stdout.buffer.write(text.encode('utf-8'))
    sys.stdout.flush()
