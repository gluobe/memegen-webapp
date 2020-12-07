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
                $code = $e->getCode();
                $error_message = $e->getMessage();
                error_log("### Error connecting to Azure tables: ".$code." - ".$error_message);
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
                $entity->addProperty("name", null, "badluckbrian");
                $entity->addProperty("date", null, "$time");
                $m->insertEntity($remoteTableName, $entity);
            } catch(MicrosoftAzure\Storage\Common\Exceptions\ServiceException $e){
                $code = $e->getCode();
                $error_message = $e->getMessage();
                error_log("### Error inserting data into Azure tables: ".$code." - ".$error_message);
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
                $code = $e->getCode();
                $error_message = $e->getMessage();
                error_log("### Error inserting data into Azure tables: ".$code." - ".$error_message);
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
            
            
            
        } else {
            error_log("### Cloud not recognized! ($cloud)");
        }
          
        // Delete temporary file
        unlink("/var/www/html/meme-generator/memes/".$imgnametargetwithext);
    }

    return array($imgnametargetnoext,$url);
}

// Method:    "POST", "PUT", "GET" etc
// Url:       "example.com/path"
// Headers:   array('Content-type: text/plain', 'Content-length: 100') 
// Data:      array("param" => "value") ==> index.php?param=value
// Token:     "ABCDEF124"
// function callAPI($method, $url, $headers = false, $data = false, $token = false)
// {
//     $curl = curl_init();
// 
//     switch ($method)
//     {
//         case "POST":
//             curl_setopt($curl, CURLOPT_POST, 1);
//             if ($data)
//                 curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
//             break;
//         case "PUT":
//             curl_setopt($curl, CURLOPT_PUT, 1);
//             break;
//         default:
//             if ($data)
//                 $url = sprintf("%s?%s", $url, http_build_query($data));
//     }
// 
//     // Add tokens optionally
//     if ($token)
//         curl_setopt($curl, CURLOPT_XOAUTH2_BEARER, $token);
//         // curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
//         // curl_setopt($curl, CURLOPT_USERPWD, "username:password");
// 
//     // Add headers optionally
//     if ($headers)
//         curl_setopt($curl, CURLOPT_HTTPHEADER, $headers); 
// 
//     curl_setopt($curl, CURLOPT_URL, $url);
//     curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
// 
//     $result = curl_exec($curl);
// 
//     curl_close($curl);
// 
//     return $result;
// }

?>
