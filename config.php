<?php

#####################
## Global Settings ##
#####################

$yourId = "<your_ID>";

$awsRegion = "eu-west-1"; # Ireland
#$awsRegion = "us-east-2"; # Ohio

$dynamoDBTable = "lab-images-table-$yourId"; # not using cloudformation
#$dynamoDBTable = "lab-cf-images-table-$yourId"; # using cloudformation

$s3Bucket = "lab-images-bucket-$yourId"; # not using cloudformation
#$s3Bucket = "lab-cf-images-bucket-$yourId"; # using cloudformation 

###################
## Site Settings ##
###################

# Wether to save data locally (mongodb) or remotely (dynamodb)
$remoteData = false; # MongoDB
#$remoteData = true; # DynamoDB

# Wether to save the memes locally or remotely (s3)
$remoteFiles = false; # locally
#$remoteFiles = true; # s3

# Wether to set site color to blue or green (used to differentiate sites from ELB)
$siteColorBlue = false; # Green
#$siteColorBlue = true; # Blue

?>