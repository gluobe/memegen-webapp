# todo
 
* Maybe get rid of composer.json, use cli instead like aws sdk
  * Or do the opposite where we do use composer.json, choose between different composer.json files or **install all cloud every time**.
* Find a way for credentials to not be so obnoxious in creds.php, is there a instance role in azure, like in aws?
  * virtual machine managed identities for vms, how to create
  * I saw you can grant storage account permissions to vm managed identities from the storage account iam interface.
* Decide on using system managed identities or user managed identities?
  * user == separate resource
  * system == linked to resource && dies with resource
  
  
# Todo after done

* Test AWS workshop with new memegen config.php
  * and composer.json changes
  

# Tutorial high level
  * [Lab 0 - Prerequisites]
    * login to console and management instance
  * [Lab 1 - AZ Portal & VM]
    * explore console and create new vm
  * [Lab 2 - Manual installation (Infra 0.0)]
    * configure new vm to run memegen app
  * [Lab 3 - Storage account tables]
    * create a table
    * configure app to use tables
    * use azure cli to get storage account access keys connection string and input them in app config
  * [Lab 4 - Load balancers]
    * set up another vm, using a script to configure it instead of manually
    * create a load balancer for the 2 vms
  * [Lab 5 - Storage account blobs]
    * create a container
    * upload an image via the azure cli
    * configure the app to use blobs
  * [Lab 6 - Route 53]
    * very short, have a look at IP resource and configure dns url for load balancer
  * [Lab 7 - vm scale sets (infra 1.0)]
    * Set up scale sets, add script to user data
    * Delete manually set up instances
    * Add lb to scale set
  * [Lab 8 - azure resource manager (infra 2.0)]
    * Deploy a script that does everything we did manually at once.
  * [Lab 9 - Chaos Engineering]
    * delete an instance or 2, watch it regenerate