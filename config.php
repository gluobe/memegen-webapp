<?php

###################
## Site Settings ##
###################

$yourId = "<your_ID>";

$cloud = "";
#$cloud = "AWS";
#$cloud = "AZ";

$region = "";
#$region = "eu-west-1"; # AWS Ireland
#$region = "westeurope"; # AZ Netherlands

$azConnectionString = "";
// $azConnectionString = "DefaultEndpointsProtocol=https;AccountName=[ACCOUNTNAME];AccountKey=[ACCOUNTKEY]"

// We use "table" as a general location to store meme data. This can be aws dynamodb table, az storageaccount table...
$remoteTableName = ""; 
#$remoteTableName = "lab-images-table-$yourId"; # using aws
#$remoteTableName = "labImagesTable$yourId"; # using az
#$remoteTableName = "lab-cf-images-table-$yourId"; # using aws cloudformation
#$remoteTableName = "labArmImagesTable$yourId"; # using using az resource manager

// We use "bucket" as a general location to store meme files. This can be aws s3 bucket, az storageaccount blob container...
$remoteBucketName = ""; 
#$remoteBucketName = "lab-images-bkt-$yourId"; # using aws
#$remoteBucketName = "lab-images-container-$yourId"; # using az
#$remoteBucketName = "lab-cf-images-bkt-$yourId"; # using aws cloudformation 
#$remoteBucketName = "lab-arm-images-container-$yourId"; # using az resource manager

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