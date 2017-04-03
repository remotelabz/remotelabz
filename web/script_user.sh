#!/bin/bash 

user_exists=$(awk -F':' '{print $1}' /etc/passwd | grep -w fnolot)
if [ -z $user_exists ] 
then
	pass=$(openssl passwd -crypt fnolot)
	$(useradd -s /bin/bash --create-home --password $pass fnolot)
fi 
$(mkdir /home/fnolot/3VM_1ASA_34)