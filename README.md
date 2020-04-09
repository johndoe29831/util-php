# util php
util php

## composer 登録

composer.json
``` json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/johndoe29831/util-php"
    }
  ],
  "require": {
    "jd29/util": "dev-master"
  }
}
```

## php 使い方
``` php
require('./vendor/autoload.php');

$array_01 = array(
    array('name'=>'aaaa'),
    array('name'=>'bbbb'),
    array('name'=>'cccc'),
);

var_dump(Jd29\Hash::get($array_01, '1.name'));
var_dump(Jd29\Hash::extract($array_01, '{n}.name'));
```
