import sys;import email;from email import policy;

sys.stdout.buffer.write(email.message_from_binary_file(sys.stdin.buffer, policy=policy.default).get_body().get_content().encode('utf-8'))
sys.stdout.flush()
