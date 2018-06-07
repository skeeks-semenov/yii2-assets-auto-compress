CHANGELOG
==============

1.4.1
-----------------
 * Logs
 * JsMinFormatter
 * CssMinFormatter
 
1.4.0
-----------------
 * Use new config!
 
Old config :
```php
'assetsAutoCompress' => [
    'class'   => '\skeeks\yii2\assetsAuto\AssetsAutoCompressComponent',
    
    'htmlCompress'                  => true, //Deprecated!!!       
    'htmlCompressOptions'           =>       //Deprecated!!!       
    [
        'extra' => false,      
        'no-comments' => true 
    ],   
],
```

New config:
```php
'assetsAutoCompress' => [
    'class'   => '\skeeks\yii2\assetsAuto\AssetsAutoCompressComponent',
    'htmlFormatter' => [
        //Enable compression html
        'class'         => 'skeeks\yii2\assetsAuto\formatters\html\TylerHtmlCompressor',
        'extra'         => false,       //use more compact algorithm
        'noComments'    => true,        //cut all the html comments
        'maxNumberRows' => 50000,       //The maximum number of rows that the formatter runs on
    
        //or
    
        'class' => 'skeeks\yii2\assetsAuto\formatters\html\MrclayHtmlCompressor',
    
        //or any other your handler implements skeeks\yii2\assetsAuto\IFormatter interface
    
        //or false
    ],
],
```
 
 * New option maxNumberRows in TylerHtmlCompressor — the maximum number of rows that the formatter runs on
 * Fixed double html conversion
 * Created skeeks\yii2\assetsAuto\formatters\html\MrclayHtmlCompressor
 * Created skeeks\yii2\assetsAuto\formatters\html\TylerHtmlCompressor
 * Added htmlFormatter config option
 * Deprecated htmlCompressOptions config option
 * Deprecated htmlCompress config option
 * Using IFormatter interface
 * Using stable versions of dependencies
 
1.3.1.2
-----------------
 * Fixed local read files
 
1.3.1.1
-----------------
 * Fixed webroot setting
 
1.3.1
-----------------
 * Fixed: https://github.com/skeeks-semenov/yii2-assets-auto-compress/issues/5
 * Fixed: https://github.com/skeeks-semenov/yii2-assets-auto-compress/issues/23
 * Add webroot setting
 * Local read files
 
1.3.0
-----------------
 * Changing the subdirectory with the code in /src
 * Fixed https://github.com/skeeks-semenov/yii2-assets-auto-compress/issues/40
 * Using user-agent header
 * Do not use @web and @webroot (using \Yii::$app->assetManager->baseUrl and \Yii::$app->assetManager->basePath)
 * Using yiisoft/yii2-httpclient
 
1.2.3.1
-----------------
 * http_code 200
 
1.2.3
-----------------
 * Do not connect the js files when all pjax requests.

1.2.2
-----------------
 * Fixed https://github.com/skeeks-semenov/yii2-assets-auto-compress/issues/6

1.2.1
-----------------
 * Html compression by default no extra

1.2.0
-----------------
 * Added html compression

1.1.2
-----------------
 * fixed bug https://github.com/skeeks-semenov/yii2-assets-auto-compress/issues/7
 * Processing of files with 404 titles

1.1.1
-----------------
  * fixed bug download css from remoute server

1.1
-----------------
  * Removed unnecessary settings preloader

1.0.4
-----------------
  * Added timeout on file reading

1.0.3
-----------------
  * Ability to insert CSS using js
  * Added ability to enable preloader
  * It adds the ability to transfer files, css bottom of the page

1.0.2
-----------------
  * Pjax requests should not be exclusion
  * Update composer (use mrclay/minify)
  
1.0.1
-----------------
  * Add setting cssCompress

1.0.0
-----------------
  * Stable release
