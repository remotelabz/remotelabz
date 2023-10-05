#!/bin/bash

. /opt/remotelabz/.env.local

device_instances=`mysql --batch -u $MYSQL_USER -h $MYSQL_SERVER -D $MYSQL_DATABASE -p$MYSQL_PASSWORD -N <<MY_QUERY
SELECT device_instance.uuid as uuid, hypervisor.name as hypervisor FROM device_instance 
JOIN device ON device_instance.device_id = device.id
JOIN hypervisor ON device.hypervisor_id = hypervisor.id
WHERE guest_id IN (SELECT id FROM invitation_code WHERE expiry_date < NOW());
MY_QUERY
`
echo "${device_instances}"

IFS='/n' read -ra instances <<< $device_instances
i=0
declare -a uuid
declare -a hypervisor

while IFS='/n' read -ra instances; do
    IFS2=' ' read -r -a properties <<< "${instances[@]}"
    uuid+=(${properties[0]})
    hypervisor+=(${properties[1]})
    i=$((i+1))
done <<< $device_instances

echo "${uuid[@]}"
echo "${hypervisor[@]}"
echo "${i}"

for ((i=0; i < ${#uuid[@]}; i++));do
    if [[ ${hypervisor[i]} == "qemu" ]]; then
        result=$(ps aux | grep -e ${uuid[i]}  | grep -e qemu | grep -v grep | awk '{print $2}')
        if [[ $result != "" ]]; then
            while IFS='/n' read -ra pid; do
                kill -9 $pid
                echo "pid: ${pid}"
            done <<< $result
        fi
    else 
        lxc-stop -n ${uuid[i]}
        echo "no qemu"
    fi
done


mysql -u $MYSQL_USER -h $MYSQL_SERVER -D $MYSQL_DATABASE -p$MYSQL_PASSWORD <<MY_QUERY
DELETE FROM lab_instance WHERE guest_id IN (SELECT id FROM invitation_code WHERE expiry_date < NOW());
DELETE FROM invitation_code WHERE expiry_date < NOW();
MY_QUERY