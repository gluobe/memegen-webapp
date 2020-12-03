<?php

# Require the files created by composer to load in SDKs.
require 'vendor/autoload.php';

# Include additional files
include 'config.php';
include 'creds.php';

# Set errors on (/var/log/apache2/error.log)
ini_set("display_errors", "On");

#######################
## Declare functions ##
#######################

// Connect to the database, either mongodb or dynamodb
function ConnectDB(){
    global $m;
    global $cloud;
    global $region;
    global $remoteData;

    if($remoteData){
        error_log("### Cloud: $cloud");
        if($cloud == "AWS"){
            // DynamoDB
            $m = Aws\DynamoDb\DynamoDbClient::factory(array(
                'region'  => (string)$region,
                'version' => "latest"
            ));
        } elseif($cloud == "AZ") {
            error_log("### bork");
            $tableRestProxy = WindowsAzure\Common\ServicesBuilder::getInstance()->createTableService();
            try {
                // Create table.
                $tableRestProxy->createTable("mytable");
            } catch(WindowsAzure\Common\ServiceException $e){
                $code = $e->getCode();
                $error_message = $e->getMessage();
                echo $code.": ".$error_message."<br />";
            }
        } elseif($cloud == "GCP") {
            
        } else {
            error_log("### Cloud not recognized! ($cloud)");
        }
    } else {
        // MongoDB
        $username="student";
        $password="Cloud247";
        $servername="localhost";

        $m = new \MongoDB\Driver\Manager("mongodb://${username}:${password}@${servername}/memegen");
    }
}

// Inserts a meme name and current date into the database, either mongodb or dynamodb
function InsertMemes($imageName,$url){
    global $m;
    global $cloud;
    global $remoteData;
    global $remoteTableName;

    $rand = rand(1,99999999);
    $time = time();

    if($remoteData){
        // DynamoDB
            //get length of db
            $iterator = $m->getIterator('Scan', array(
              'TableName' => "$remoteTableName"
            ));
            $id = (string)$time.(string)$rand;
            // Insert data in the images table
            $insertResult = $m->putItem(array(
                'TableName' => "$remoteTableName",
                'Item' => array(
                    'id'      => array('N' => (string)$id),
                    'name'    => array('S' => $imageName),
                    'date'    => array('S' => (string)$time),
                    'url'     => array('S' => $url)
                )
            ));
    } else {
        // MongoDB
            // Insert into memegen db and images collection
            $bulk = new MongoDB\Driver\BulkWrite;
            $bulk->insert([
                            'name'  => array('S' => $imageName),
                            'date'  => array('S' => (string)$time),
                            'url'     => array('S' => $url)
                          ]);
            $m->executeBulkWrite('memegen.images', $bulk);
    }
}

// Gets all memes and encodes and echo's it so ajax can catch it.
function GetMemes(){
    global $m;
    global $cloud;
    global $remoteBucketName;
    global $remoteTableName;
    global $remoteFiles;
    global $remoteData;

    // If data is stored remotely, use dynamodb, else mongodb
    if($remoteData){
        // DynamoDB
        $iterator = $m->getIterator('Scan', array(
          'TableName' => "$remoteTableName"
        ));

        echo json_encode(iterator_to_array($iterator));
    } else {
        // MongoDB
        $filter = [];
        $options = [];
        $query = new MongoDB\Driver\Query($filter, $options);
        $iterator = $m->executeQuery('memegen.images', $query);

        echo json_encode(iterator_to_array($iterator));
    }
}

// Generates a meme with the python script and either puts it locally or in an S3 bucket
function generateMeme($top, $bot, $imgname){
  global $m;
  global $cloud;
  global $remoteBucketName;
  global $remoteFiles;
  global $region;
  # Save current dir and go into python dir
    $olddir = getcwd();
    chdir("meme-generator");

    # Create full imagenames
    $rand = rand(1,999);
    $imgnameorig = $imgname . ".jpg";
    # Remove nasty chars for meme picture
    $top = preg_replace('/[\'\"]+/', '', $top);
    $bot = preg_replace('/[\'\"]+/', '', $bot);
    # No extension variable image name
    $imgnametargetnoext = $imgname . "-" . $top . "-" . $bot . "-" . $rand;
    # Replace nasty characters for filename
    $imgnametargetnoext = preg_replace('/[^-.0-9\w]+/', '', $imgnametargetnoext);
    # With extension variable image name
    $imgnametargetwithext = $imgnametargetnoext . ".jpg";

    # Execute meme generator python command
    $command = "python3 memegen.py '$top' '$bot' '$imgnameorig' '$imgnametargetwithext' 2>&1";
    $commandoutput = exec($command, $out, $status);

    $image = fopen("/var/www/html/meme-generator/memes/".$imgnametargetwithext,'r');
    # Go back to original dir
    chdir($olddir);

    $url = "no url";

    if($remoteFiles){
        // sync to s3
        $sdk = new Aws\Sdk([
            'region'   => (string)$region,
            'version'  => 'latest',
        ]);
        // Use an Aws\Sdk class to create the S3Client object.
        $s3Client = $sdk->createS3();

        // Send a PutObject request and get the result object.
        $result = $s3Client->putObject([
            'Bucket' => $remoteBucketName,
            'Key'    => $imgnametargetwithext,
            'Body'   => $image
        ]);

        // Get the url from the s3 stored image.
        $url = $s3Client->getObjectUrl ( $remoteBucketName, $imgnametargetwithext );

        // Delete temporary file
        unlink("/var/www/html/meme-generator/memes/".$imgnametargetwithext);
    }

    return array($imgnametargetnoext,$url);
}

?>
