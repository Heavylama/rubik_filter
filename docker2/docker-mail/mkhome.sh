#/bin/bash


MAIL_HOME_DIR=../volumes/mail/home

print_help() {
	echo 'Creates ldif users home directories in appropriate volume folder'
	echo 'Usage: mkhome ldif_file'
}

for arg in "$@"
do
	if [ "$arg" == "-h" ]; then
		print_help
		exit 0
	fi
done


if [ "$#" == "0" ]; then
	print_help
	exit -1
fi

REGEX="^((mailHomeDirectory)|(mailUidNumber)|(mailGidNumber))"

grep -i "$REGEX" "$1" | sed "s/^$REGEX: //i"


