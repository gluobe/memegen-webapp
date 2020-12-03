# todo
 
* Maybe get rid of composer.json, use cli instead like aws sdk
  * Or do the opposite where we do use composer.json, choose between different composer.json files or install all cloud every time.
* Find a way for credentials to not be so obnoxious in creds.php, is there a instance role in azure, like in aws?
  * virtual machine managed identities for vms, how to create
  * I saw you can grant storage account permissions to vm managed identities from the storage account iam interface.
* Decide on using system managed identities or user managed identities?
  * user == separate resource
  * system == linked to resource && dies with resource