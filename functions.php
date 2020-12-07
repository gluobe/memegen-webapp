<?php

# Require the files created by composer to load in SDKs.
require 'vendor/autoload.php';

# Include additional files
include 'config.php';

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
    global $azConnectionString;

    if($remoteData){
      
        // Connect to AWS DynamoDB
        if($cloud == "AWS"){
            $m = Aws\DynamoDb\DynamoDbClient::factory(array(
                'region'  => (string)$region,
                'version' => "latest"
            ));
            
        // Connect to Azure Storage Account Tables
        } elseif($cloud == "AZ") {
            try {
                $m = WindowsAzure\Common\ServicesBuilder::getInstance()->createTableService($azConnectionString);
            } catch(WindowsAzure\Common\ServiceException $e){
                error_log("### Error connecting to Azure tables: ".$e->getCode()." - ".$e->getMessage());
            }
            
        } else {
            error_log("### Cloud not recognized! ($cloud)");
        }
    } else {
        // Connect to local MongoDB
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
    $id = (string)$time.(string)$rand;

    if($remoteData){
      
        // Insert data to AWS DynamoDB
        if($cloud == "AWS"){
            $m->putItem(array(
                'TableName' => "$remoteTableName",
                'Item' => array(
                    'id'      => array('N' => (string)$id),
                    'name'    => array('S' => $imageName),
                    'date'    => array('S' => (string)$time),
                    'url'     => array('S' => $url)
                )
            ));
            
        // Insert data to Azure Storage Account Tables
        } elseif($cloud == "AZ") {
            try {
                $entity = new MicrosoftAzure\Storage\Table\Models\Entity();
                $entity->setPartitionKey("images");
                $entity->setRowKey("$id");
                $entity->addProperty("name", null, "$imageName");
                $entity->addProperty("date", null, "$time");
                $entity->addProperty("url", null, "$url");
                $m->insertEntity($remoteTableName, $entity);
            } catch(MicrosoftAzure\Storage\Common\Exceptions\ServiceException $e){
                error_log("### Error inserting data into Azure storage account tables: ".$e->getCode()." - ".$e->getMessage());
            }

        } else {
            error_log("### Cloud not recognized! ($cloud)");
        }
    } else {
        // MongoDB
        // Insert into memegen db and images collection
        $bulk = new MongoDB\Driver\BulkWrite;
        $bulk->insert([
          'name'  => array('S' => $imageName),
          'date'  => array('S' => (string)$time),
          'url'   => array('S' => $url)
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
      
        // Get data from AWS DynamoDB
        if($cloud == "AWS"){
            $iterator = $m->getIterator('Scan', array(
              'TableName' => "$remoteTableName"
            ));
            echo json_encode(iterator_to_array($iterator));
      
        // Get data from Azure Storage Account Tables
        } elseif($cloud == "AZ") {
            try {
                $result = $m->queryEntities($remoteTableName, "PartitionKey eq 'images'");
            } catch(MicrosoftAzure\Storage\Common\Exceptions\ServiceException $e){
                error_log("### Error getting data from Azure storage account tables: ".$e->getCode()." - ".$e->getMessage());
            }
            $entities = $result->getEntities();
            
            // Format the data in a way that works with the site (index.php). index.php was first made with AWS and DynamoDB, so we use that format.
            $iterator = [];
            foreach($entities as $entity){
                $entityArray = array(
                  'id'      => array('N' => (string)$entity->getTimestamp()->format("U")),
                  'name'    => array('S' => $entity->getProperty("name")->getValue()),
                  'date'    => array('S' => (string)$entity->getTimestamp()->format("U")),
                  'url'     => array('S' => $entity->getProperty("date")->getValue())
                );

                // Append entityArray element to iterator array.
                $iterator[] = $entityArray;
            }
            echo json_encode($iterator);
            
        } else {
            error_log("### Cloud not recognized! ($cloud)");
        }
    } else {
        // Get data from local MongoDB
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
    global $azConnectionString;
    
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
      
        // Get data from AWS DynamoDB
        if($cloud == "AWS"){
            $sdk = new Aws\Sdk([
                'region'   => (string)$region,
                'version'  => 'latest',
            ]);
            $s3Client = $sdk->createS3();

            // Upload the file to S3
            $s3Client->putObject([
                'Bucket' => $remoteBucketName,
                'Key'    => $imgnametargetwithext,
                'Body'   => $image
            ]);

            // Get the url from the s3 stored image.
            $url = $s3Client->getObjectUrl ( $remoteBucketName, $imgnametargetwithext );
            
        // Get data from Azure Storage Account Tables
        } elseif($cloud == "AZ") {
            global $b;
            
            try {
                $b = WindowsAzure\Common\ServicesBuilder::getInstance()->createBlobService($azConnectionString);
            } catch(WindowsAzure\Common\ServiceException $e){
                error_log("### Error creating Azure blob instance: ".$e->getCode()." - ".$e->getMessage());
            }
            
            try {
                // Upload blob to blob container
                $b->createBlockBlob($remoteBucketName, $imgnametargetwithext, $image);
            } catch(MicrosoftAzure\Storage\Common\ServiceException $e){
                error_log("### Error uploading data to Azure storage account blob container: ".$e->getCode()." - ".$e->getMessage());
            }
            
            try {
                // Get blob data to pull blob URL
                $containerClient = $b->getContainerClient($remoteBucketName);
                $blob = $containerClient->getBlockBlobClient($imgnametargetwithext);
                // Set content type correctly
                $opts = new SetBlobPropertiesOptions();
                $opts->setContentType('image/png');
                $blob->setBlobProperties($remoteBucketName, $imgnametargetwithext, $opts);
                // Set url 
                $url = $blob->getUrl();
            } catch(MicrosoftAzure\Storage\Common\ServiceException $e){
                error_log("### Error getting blob properties after uploading image to Azure storage account blob container: ".$e->getCode()." - ".$e->getMessage());
            }
            
        } else {
            error_log("### Cloud not recognized! ($cloud)");
        }
          
        // Delete temporary file because it was uploaded and no longer needed locally
        unlink("/var/www/html/meme-generator/memes/".$imgnametargetwithext);
    }

    return array($imgnametargetnoext,$url);
}

?>
