# Application README #

![](images/PHPApplicationSchema.png?raw=true)
![](images/PHPApplicationScreenshot.png?raw=true)

### What? ###

* This application folder houses the Gluo PHP Meme Generator which is used as an example application in other workshops.

* The whole point of the application is that its config.php can be switched at will and the application will behave differently dynamically.
    * Changing `$remoteData` from false to true, means it will no longer use its local MongoDB and choose DynamoDB.
    * Changing `$remoteFiles` from false to true, means it will use S3 in addition to storing images locally.
    * Changing `$siteColorBlue` from false to true, means the site color changes for when multiple instances with the same application are running.
