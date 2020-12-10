#!/bin/bash
# Installs MemeGen for AWS, modified to be used for cloudformation.

# Print each command and exit as soon as something fails.
set -ex

YOURID=$1
CLOUD="AWS"
REGION=$2
PHP_VERSION=7.4
TABLENAME="lab-cf-images-table-$YOURID"
BUCKETNAME="lab-cf-images-bkt-$YOURID"

# Set a settings for non interactive mode
  export DEBIAN_FRONTEND=noninteractive
  export PATH=$PATH:/usr/local/sbin/
  export PATH=$PATH:/usr/sbin/
  export PATH=$PATH:/sbin

# Update the server
  apt-get update -y && apt-get upgrade -y
  apt-get install -y jq

# Install latest mongodb repo
  wget -qO - https://www.mongodb.org/static/pgp/server-4.4.asc | apt-key add -
  echo "deb [ arch=amd64,arm64 ] https://repo.mongodb.org/apt/ubuntu focal/mongodb-org/4.4 multiverse" | sudo tee /etc/apt/sources.list.d/mongodb-org-4.4.list
  apt-get update -y

# Install packages (apache, mongo, php, python and other useful packages)
  apt-get install -y apache2 composer mongodb-org mongodb-org-server php$PHP_VERSION php$PHP_VERSION-dev libapache2-mod-php$PHP_VERSION php$PHP_VERSION-curl php-pear pkg-config libssl-dev libssl-dev python3-pip imagemagick wget unzip;

# Mongodb config
  pecl install mongodb
  echo "extension=mongodb.so" >> /etc/php/$PHP_VERSION/apache2/php.ini && echo "extension=mongodb.so" >> /etc/php/$PHP_VERSION/cli/php.ini

# Pip install meme creation packages and awscli for syncing s3 to local fs
  pip3 install wand awscli

# Enable and start services
  # Enable
  systemctl enable mongod apache2
  # Start
  systemctl start mongod apache2
  
  # Wait for mongod start
  until nc -z localhost 27017
  do
      echo 'Sleep till MongoDB is running.' | systemd-cat
      sleep 2
  done

# Configure Mongodb
  # Create mongo student root user for memegen db
  echo '
    use memegen
    db.createUser(
       {
         user: "student",
         pwd: "Cloud247",
         roles: [ { role: "root", db: "admin" } ]
       }
    )
  ' | mongo
  # Enable user credentials security
  echo "security:" >> /etc/mongod.conf && echo "  authorization: enabled" >> /etc/mongod.conf
  # Restart the mongodb service
  systemctl restart mongod

# Download and install MemeGen
  # Git clone the repository in your home directory
  git clone https://github.com/gluobe/memegen-webapp-aws.git ~/memegen-webapp
  # Clone the application out of the repo to the web folder.
  cp -r ~/memegen-webapp/* /var/www/html/
  # Set permissions for apache
  chown -R www-data:www-data /var/www/html/meme-generator/

# Install cloud sdks (We shouldn't do this as root but it doesn't really matter for the purposes of this workshop.)
  wget https://getcomposer.org/composer-stable.phar -O /usr/local/bin/composer
  chmod +x /usr/local/bin/composer
  COMPOSER_HOME=/var/www/html composer install -d /var/www/html

# Configure httpd and restart
  # Remove index.html
  rm -f /var/www/html/index.html
  # Restart httpd
  systemctl restart apache2

# Edit site's config.php file
  # Put remote on.
  sed -i 's@^$remoteData.*@$remoteData = true; # DynamoDB (Altered by sed)@g' /var/www/html/config.php
  sed -i 's@^$remoteFiles.*@$remoteFiles = true; # S3 (Altered by sed)@g' /var/www/html/config.php

  # Alter user id
  sed -i "s@^\$yourId.*@\$yourId = \"$YOURID\"; # (Altered by sed)@g" /var/www/html/config.php
  sed -i "s@^\$cloud.*@\$cloud = \"$CLOUD\"; # (Altered by sed)@g" /var/www/html/config.php
  sed -i "s@^\$region.*@\$region = \"$REGION\"; # (Altered by sed)@g" /var/www/html/config.php
  sed -i "s@^\$remoteTableName.*@\$remoteTableName = \"$TABLENAME\"; # (Altered by sed)@g" /var/www/html/config.php
  sed -i "s@^\$remoteBucketName.*@\$remoteBucketName = \"$BUCKETNAME\"; # (Altered by sed)@g" /var/www/html/config.php
  sed -i 's@^$remoteData.*@$remoteData = true; # (Altered by sed)@g' /var/www/html/config.php
  sed -i 's@^$remoteFiles.*@$remoteFiles = true; # (Altered by sed)@g' /var/www/html/config.php

# Please go to http://
  echo -e "Automatic MemeGen installation complete."
  echo 'Automatic MemeGen installation complete.' | systemd-cat