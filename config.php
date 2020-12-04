<?php

###################
## Site Settings ##
###################

$yourId = "<your_ID>";

$cloud = "";
#$cloud = "AWS";
#$cloud = "AZ";
#$cloud = "GCP";

$region = "";
#$region = "eu-west-1"; # AWS Ireland
#$region = "westeurope"; # AZ Netherlands

$azConnectionString = "";
// $azConnectionString = "DefaultEndpointsProtocol=https;AccountName=[ACCOUNTNAME];AccountKey=[ACCOUNTKEY];EndpointSuffix=core.windows.net"

// We use "table" as a general location to store meme data. This can be aws dynamodb table, az storageaccount table...
$remoteTableName = "lab-images-table-$yourId"; 
#$remoteTableName = "lab-cf-images-table-$yourId"; # using aws cloudformation
#$remoteTableName = "lab-arm-images-table-$yourId"; # using using azure resource manager
#$remoteTableName = "lab-dm-images-table-$yourId"; # using gcp deployment manager  

// We use "bucket" as a general location to store meme files. This can be aws s3 bucket, az storageaccount blob...
$remoteBucketName = "lab-images-bkt-$yourId"; 
#$remoteBucketName = "lab-cf-images-bkt-$yourId"; # using aws cloudformation 
#$remoteBucketName = "lab-arm-images-bkt-$yourId"; # using azure resource manager
#$remoteBucketName = "lab-dm-images-bkt-$yourId"; # using gcp deployment manager  

# Wether to save data locally (mongodb) or remotely (cloud)
$remoteData = false; # local
#$remoteData = true; # cloud

# Wether to save the memes locally or remotely (cloud)
$remoteFiles = false; # local
#$remoteFiles = true; # cloud

# Wether to set site color to blue or green (used to differentiate sites from load balancing)
$siteColorBlue = false; # Green
#$siteColorBlue = true; # Blue

?>