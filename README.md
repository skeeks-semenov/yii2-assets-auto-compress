Automatically compile and merge files js + css
===================================

This solution enables you to dynamically combine js and css files to optimize the html page.
This allows you to improve the performance of google page speed

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist skeeks/yii2-assets-auto-compress "*"
```

or add

```
"skeeks/yii2-assets-auto-compress": "*"
```


How to use
----------

```php
//App config
[
    'bootstrap'    => ['assetsAutoCompress'],
    'components'    =>
    [
    //....
        'assetsAutoCompress' =>
        [
            'class'         => '\skeeks\yii2\assetsAuto\AssetsAutoCompressComponent',
        ],
    //....
    ]
]

```



```php
//App config
[
    'bootstrap'    => ['assetsAutoCompress'],
    'components'    =>
    [
    //....
        'assetsAutoCompress' =>
        [
            'class'             => '\skeeks\yii2\assetsAuto\AssetsAutoCompressComponent',
            'enabled'           => true,
            'jsCompress'        => true,
            'cssFileCompile'    => true,
            'jsFileCompile'     => true,
        ],
    //....
    ]
]

```


Demo (view source code)
----------
* http://skeeks.com/
* http://select-moto.ru/
* http://motopraktika.ru/


##Screenshot
[![SkeekS CMS admin panel](http://marketplace.cms.skeeks.com/uploads/all/b7/5e/8b/b75e8b31bfda1686d950c7b8783b53b5.png)](http://marketplace.cms.skeeks.com/uploads/all/b7/5e/8b/b75e8b31bfda1686d950c7b8783b53b5.png)

___

[![SkeekS CMS admin panel](http://marketplace.cms.skeeks.com/uploads/all/3d/8c/aa/3d8caa7df0ef5cb0dd5149f5a5bdebba.png)](http://marketplace.cms.skeeks.com/uploads/all/3d/8c/aa/3d8caa7df0ef5cb0dd5149f5a5bdebba.png)

___

[![SkeekS CMS admin panel](http://marketplace.cms.skeeks.com/uploads/all/6f/77/39/6f7739f74f93dc6c82be15bdc86355a9.png)](http://marketplace.cms.skeeks.com/uploads/all/6f/77/39/6f7739f74f93dc6c82be15bdc86355a9.png)

___

[![SkeekS CMS admin panel](http://marketplace.cms.skeeks.com/uploads/all/0e/08/ff/0e08ffc6d46a1ffa1683c32e8f916d67.png)](http://marketplace.cms.skeeks.com/uploads/all/0e/08/ff/0e08ffc6d46a1ffa1683c32e8f916d67.png)


___

> [![skeeks!](https://gravatar.com/userimage/74431132/13d04d83218593564422770b616e5622.jpg)](http://skeeks.com)  
<i>SkeekS CMS (Yii2) — fast, simple, effective!</i>  
[skeeks.com](http://skeeks.com) | [cms.skeeks.com](http://cms.skeeks.com) | [marketplace.cms.skeeks.com](http://marketplace.cms.skeeks.com)

