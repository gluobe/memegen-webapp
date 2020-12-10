#!/bin/bash
# Installs MemeGen, modified to be used in a Launch Configuration.

# Print each command and exit as soon as something fails.
set -ex

###############################################
#### STUDENTS, please change this variable ####
###############################################
YOURID="<your_ID>"

CLOUD="AZ"
TABLENAME="labImagesTable$YOURID"
BUCKETNAME="lab-images-container-$YOURID"
PHP_VERSION=7.4


# Set a settings for non interactive mode
  export DEBIAN_FRONTEND=noninteractive
	
# Update the server
  apt-get update -y && apt-get upgrade -y
  apt-get install -y jq

# Set variables (after jq is installed)
  INSTANCEMETADATA=$(curl -s -H "Metadata:true" http://169.254.169.254/metadata/instance?api-version=2020-09-01)
  RESOURCEGROUPNAME=$(echo $INSTANCEMETADATA | jq -r '.compute.resourceGroupName')
  REGION=$(echo $INSTANCEMETADATA | jq -r '.compute.location')

# Install latest mongodb repo
  wget -qO - https://www.mongodb.org/static/pgp/server-4.4.asc | apt-key add -
  echo "deb [ arch=amd64,arm64 ] https://repo.mongodb.org/apt/ubuntu focal/mongodb-org/4.4 multiverse" | sudo tee /etc/apt/sources.list.d/mongodb-org-4.4.list
  apt-get update -y 

# Install packages (apache, mongo, php, python and other useful packages)
  apt-get install -y apache2 mongodb-org mongodb-org-server php$PHP_VERSION php$PHP_VERSION-dev libapache2-mod-php$PHP_VERSION php-pear pkg-config libssl-dev libssl-dev python3-pip imagemagick wget unzip
  
  # Mongodb config
  pecl install mongodb
  echo "extension=mongodb.so" >> /etc/php/$PHP_VERSION/apache2/php.ini && echo "extension=mongodb.so" >> /etc/php/$PHP_VERSION/cli/php.ini

# Install python packages, wand is used to alter images with text
  pip3 install wand
  
# Enable and start services  
  # Enable
  systemctl enable mongod apache2
  # Start
  systemctl start mongod apache2
  # Wait for mongod start
  until nc -z localhost 27017
  do
      sleep 1
      echo "Waiting for MongoDB to be available..."
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
  git clone --single-branch --branch azure-integrations https://github.com/gluobe/memegen-webapp-aws.git ~/memegen-webapp
  # Clone the application out of the repo to the web folder.
  cp -r ~/memegen-webapp/* /var/www/html/
  # Set permissions for apache
  chown -R www-data:www-data /var/www/html/meme-generator/
  
# Install azure cli
  apt-get install -y azure-cli

# Get storage account credentials
  # Use the system managed identity as login
  az login --identity
  # Take the first storageaccount from the resource group, there should only be one.
  STORAGEACCOUNTNAME=$(az storage account list --resource-group $RESOURCEGROUPNAME | jq -r '.[0].name')
  # Pull storage account connectionstring, leave out the second field (EndpointSuffix)
  CONNECTIONSTRING=$(az storage account show-connection-string --name $STORAGEACCOUNTNAME | jq -r '.connectionString' | cut -d';' --complement -f2)
  # Write storage account connection string to config file
  sed -i "s@^\$azConnectionString.*@\$azConnectionString = \"$CONNECTIONSTRING\"; # (Altered by sed)@g" /var/www/html/config.php
  
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

