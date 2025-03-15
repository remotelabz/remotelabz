#!/bin/bash
git fetch
git reset --hard
git pull
bin/remotelabz-update.sh
