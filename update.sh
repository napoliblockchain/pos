#!/bin/bash
echo Updating...
git stash
git pull

if [ ! -d "assets" ]; then
    mkdir assets
fi
if [ ! -d "uploads" ]; then
    mkdir uploads
fi

chown -R www-data:www-data assets/
chown -R www-data:www-data uploads/
chown -R www-data:www-data protected/runtime/
chown -R www-data:www-data protected/qrcodes/
chown -R www-data:www-data protected/log/

chmod 755 protected/yiic
chmod 755 update.sh
chmod +x update.sh

echo Versioning...
git rev-parse HEAD>version.txt
echo Done!
