#!/bin/bash

. /opt/remotelabz/.env.local

mysql -u $MYSQL_USER -h $MYSQL_SERVER -D $MYSQL_DATABASE -p$MYSQL_PASSWORD <<MY_QUERY
DELETE FROM invitation_code WHERE expiry_date < NOW();
MY_QUERY