API CRUD Bundle
=

CRUD Services for APIs in Symfony 5.1+ <br>
It includes:
- <b>Base controller:</b> Base controller for CRUD operations (create, read, update and delete / inactive).
- <b>Base type:</b> Base type for validations.
- <b>Paginator:</b> Service to return paginated lists.
- <b>Violation Util:</b> Validate data types and validation rules.

### Install

1. Configure the repository in the `composer.json` file: <br>
```
...

"repositories": [
   {
      "type": "vcs",
      "url":  "https://github.com/experteam-mx/api-crud-bundle.git"
   }
]  
```

2. Configure the required package in the `composer.json` file: <br>
```
"require": {
   "experteam/api-crud-bundle": "dev-master"
}
```

3. Execute the following command: <br>
```
composer update experteam/api-crud-bundle
```




### License
[MIT license](https://opensource.org/licenses/MIT).
