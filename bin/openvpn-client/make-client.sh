#!/bin/bash -e
KEY_COUNTRY="FR"
KEY_PROVINCE="MARNE"
KEY_CITY="Reims"
KEY_ORG="master-reseaux-telecom.fr"
KEY_EMAIL="admin@domaine.org"
KEY_ALGO="rsa"
KEY_LENGTH=4096
KEY_CN="RemoteLabz-VPNServer"

LOGIN=$1
PASSWORD=$2
VALIDITY=$3
CERT_CLIENT_DIR="./client-files"
CACERT="/etc/openvpn/server/ca.crt"
CAKEY="/etc/openvpn/server/ca.key"
SERVCERT="/etc/openvpn/server/$KEY_CN.crt"
SERVKEY="/etc/openvpn/server/$KEY_CN.key"
TAKEY="/etc/openvpn/server/ta.key"
usage()
{
echo " Usage: ./$0 login password certificat_validity"
exit 1

}

[ ! $# -eq 3 ] && usage
[ ! -d $CERT_CLIENT_DIR ] && mkdir $CERT_CLIENT_DIR

if [ -f $CERT_CLIENT_DIR/$LOGIN.key ]; then
echo " client exist! choose another"
exit 2
fi
echo $LOGIN > $CERT_CLIENT_DIR/$LOGIN.txt
echo $PASSWORD >> $CERT_CLIENT_DIR/$LOGIN.txt

openssl req -out $CERT_CLIENT_DIR/$LOGIN.req -new -newkey $KEY_ALGO:$KEY_LENGTH -nodes -keyout $CERT_CLIENT_DIR/$LOGIN.key -subj "/C=$KEY_COUNTRY/ST=$KEY_PROVINCE/L=$KEY_CITY/O=$KEY_ORG/CN=$KEY_CN"
openssl x509 -req -days $VALIDITY -in $CERT_CLIENT_DIR/$LOGIN.req -out $CERT_CLIENT_DIR/$LOGIN.crt -CA $CACERT -CAkey $CAKEY -CAcreateserial

echo "client
dev tun
dev-type tun
tun-mtu 1500
cipher AES-256-GCM
remote 192.168.11.131
resolv-retry infinite
key-direction 1
nobind
persist-key
persist-tun
verb 1
keepalive 10 120
port 1194
proto udp
comp-lzo
<ca>" > $LOGIN.ovpn
cat $CACERT >> $LOGIN.ovpn
echo "</ca>
<cert>" >> $LOGIN.ovpn
cat $CERT_CLIENT_DIR/$LOGIN.crt >> $LOGIN.ovpn
echo "</cert>
<key>" >> $LOGIN.ovpn
cat $CERT_CLIENT_DIR/$LOGIN.key >> $LOGIN.ovpn
echo "</key>
<tls-auth>" >> $LOGIN.ovpn
cat $TAKEY >> $LOGIN.ovpn
echo "</tls-auth>" >> $LOGIN.ovpn
