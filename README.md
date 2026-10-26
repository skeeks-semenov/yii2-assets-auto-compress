Automatically compile and merge files js + css + html in yii2 project.
===================================

This solution enables you to dynamically combine js and css files to optimize the html page.
This allows you to improve the performance of google page speed.

This tool only works on real sites. On the local projects is not working!

[![Latest Stable Version](https://img.shields.io/packagist/v/skeeks/yii2-assets-auto-compress.svg)](https://packagist.org/packages/skeeks/yii2-assets-auto-compress)
[![Total Downloads](https://img.shields.io/packagist/dt/skeeks/yii2-assets-auto-compress.svg)](https://packagist.org/packages/skeeks/yii2-assets-auto-compress)


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
//App config with all options
[
    'bootstrap'  => ['assetsAutoCompress'],
    'components' => [
        //....
        'assetsAutoCompress' => [
            'class'   => '\skeeks\yii2\assetsAuto\AssetsAutoCompressComponent',
            'enabled' => true,

            'readFileTimeout' => 3,           //Time in seconds for reading each asset file

            'jsCompress'                => true,        //Enable minification js in html code
            'jsCompressFlaggedComments' => true,        //Cut comments during processing js

            'cssCompress' => true,        //Enable minification css in html code
            
            'cssFileCompile'        => true,        //Turning association css files
            'cssFileCompileByGroups' => false       //Enables the compilation of files in groups rather than in a single file. Works only when the $cssFileCompile option is enabled
            'cssFileRemouteCompile' => false,       //Trying to get css files to which the specified path as the remote file, skchat him to her.
            'cssFileCompress'       => true,        //Enable compression and processing before being stored in the css file
            'cssFileBottom'         => false,       //Moving down the page css files
            'cssFileBottomLoadOnJs' => false,       //Transfer css file down the page and uploading them using js

            'jsFileCompile'                 => true,        //Turning association js files
            'jsFileCompileByGroups'         => false        //Enables the compilation of files in groups rather than in a single file. Works only when the $jsFileCompile option is enabled
            'jsFileRemouteCompile'          => false,       //Trying to get a js files to which the specified path as the remote file, skchat him to her.
            'jsFileCompress'                => true,        //Enable compression and processing js before saving a file
            'jsFileCompressFlaggedComments' => true,        //Cut comments during processing js

            'noIncludeJsFilesOnPjax' => true,        //Do not connect the js files when all pjax requests when all pjax requests when enabled jsFileCompile
            'noIncludeCssFilesOnPjax' => true,        //Do not connect the css files when all pjax requests when all pjax requests when enabled cssFileCompile

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
        //....
    ],
];

```


Compiled files and cleanup
----------

Compiled files are named by their content: `assets/css-compress/<md5 of content>.css`
and `assets/js-compress/<md5 of content>.js`, without `?v=`. The set of source URLs
(they carry asset directory hashes and `?v=mtime`) maps to a compiled file through a
small index entry `assets/*-compress/.index/<key>`. Republishing sources without
changing them (a deploy `touch`, a package reinstall) adds an index entry, not a
new copy of the file. Files and index entries are written to `.tmp-*` and renamed.

A page hit refreshes the mtime of its index entry and compiled file at most once per
`usageTouchInterval` (1 hour). That mtime is the usage mark for cleanup; atime and
ctime are not used (backups and `chown -R` change them).

`CompiledFilesCleaner` removes files no page has used for `days` (7) days:
expired index entries, compiled files no live entry points to and that are older
than the retention, and `.tmp-<16 hex>` files older than an hour. Files of the
previous naming scheme have no index entry; they are removed only when the retention
has also passed since the switch (`.index/.since`), so HTML rendered before the
upgrade keeps its files. Without `.since` no compiled file is removed (expired index
entries and stale temporary files still are). Entries with other names are left
alone. It reads directory listings, stat() and the small index entries, never the
compiled content. If a directory or a live index entry cannot be read, no compiled
file of that directory is removed and the run reports an error.

Pages refresh usage marks and store files under a shared `flock` on
`.index/.lock`; cleanup re-checks the mark and unlinks under an exclusive one. A page
that marked a file in time keeps it; a page that comes too late finds it gone and
compiles again instead of recreating it empty. Only the hourly mark refresh and a
store take the lock; an ordinary hit does not, and only when its mark is known to be
fresh (a vanished index entry is a miss). If the lock cannot be taken, the page uses
its source files (with a warning in the log); if `touch()` is refused (a file of
another owner), the store rewrites the file, which gives it a fresh mark. The
compiled file is marked before its index entry, so a fresh entry always points to a
fresh file.

`getSettingsHash()` is part of the index key and lists the options that change the
compiled content (and the resolved `webroot`). A subclass adding such an option must
extend it.

```php
//Console config
'controllerMap' => [
    'assets-auto-compress' => [
        'class'      => \skeeks\yii2\assetsAuto\console\CleanupController::class,
        'assetsDirs' => ['@frontend/web/assets'],
    ],
],
```

```
php yii assets-auto-compress/cleanup --dry-run
php yii assets-auto-compress/cleanup --days=7
```

A removed compiled file is rebuilt by the next page that renders it; a direct request
to it from an old HTML page gets 404, which is why the retention counts from the last use.
`skeeks/cms-assets-auto-compress` runs the cleanup daily as a `cms-job` job.

Tests: `php tests/compiled-files.php vendor/autoload.php`.


Links
----------
* [Github](https://github.com/skeeks-semenov/yii2-assets-auto-compress)
* [Changelog](https://github.com/skeeks-semenov/yii2-assets-auto-compress/blob/master/CHANGELOG.md)
* [Issues](https://github.com/skeeks-semenov/yii2-assets-auto-compress/issues)
* [Packagist](https://packagist.org/packages/skeeks/yii2-assets-auto-compress)


Demo (view source code)
----------
* [https://gallery.world](https://gallery.world)
* [http://skeeks.com/](https://skeeks.com)
* [http://select-moto.ru/](https://select-moto.ru)
* [http://motopraktika.ru/](https://motopraktika.ru)


Screenshot
------------
[![SkeekS CMS admin panel](http://marketplace.cms.skeeks.com/uploads/all/b7/5e/8b/b75e8b31bfda1686d950c7b8783b53b5.png)](http://marketplace.cms.skeeks.com/uploads/all/b7/5e/8b/b75e8b31bfda1686d950c7b8783b53b5.png)

___

[![SkeekS CMS admin panel](http://marketplace.cms.skeeks.com/uploads/all/3d/8c/aa/3d8caa7df0ef5cb0dd5149f5a5bdebba.png)](http://marketplace.cms.skeeks.com/uploads/all/3d/8c/aa/3d8caa7df0ef5cb0dd5149f5a5bdebba.png)

___

[![SkeekS CMS admin panel](http://marketplace.cms.skeeks.com/uploads/all/6f/77/39/6f7739f74f93dc6c82be15bdc86355a9.png)](http://marketplace.cms.skeeks.com/uploads/all/6f/77/39/6f7739f74f93dc6c82be15bdc86355a9.png)

___

[![SkeekS CMS admin panel](http://marketplace.cms.skeeks.com/uploads/all/0e/08/ff/0e08ffc6d46a1ffa1683c32e8f916d67.png)](http://marketplace.cms.skeeks.com/uploads/all/0e/08/ff/0e08ffc6d46a1ffa1683c32e8f916d67.png)


___

> [![skeeks!](https://skeeks.com/img/logo/logo-no-title-80px.png)](https://skeeks.com)  
<i>SkeekS CMS (Yii2) — fast, simple, effective!</i>  
[skeeks.com](https://skeeks.com) | [cms.skeeks.com](https://cms.skeeks.com)


CSS: отказ от обязательного mrclay/minify
---------------------------------------

CSS-обработка перенесена из mrclay/minify 3.0.14 в отдельную папку нашего пакета
без изменения алгоритмов. Это убирает обязательную цепочку к abandoned-пакету
marcusschwarz/lesserphp, который не нужен для обработки CSS. Лицензия и авторство
сохранены. Причины, состав, ограничения, обновление сайтов и будущий план перехода
на matthiasmullie/minify описаны в [документации](src/vendor/mrclay/README.md).

Важно: старый альтернативный MrclayHtmlCompressor требует явной установки
mrclay/minify; основной TylerHtmlCompressor этой зависимости не требует.