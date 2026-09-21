<?php
define('YII_ENABLE_ERROR_HANDLER', false);
// Usage: php tests/css-integration.php /path/to/vendor/autoload.php
$loader = require $argv[1];
$loader->addPsr4('skeeks\\yii2\\assetsAuto\\', __DIR__ . '/../src', true);
require dirname($argv[1]) . '/yiisoft/yii2/Yii.php';
require __DIR__ . '/../src/AssetsAutoCompressComponent.php';
spl_autoload_register(function ($class) {
    if (strpos($class, 'Minify_') === 0 || $class === 'lessc') {
        throw new RuntimeException('Unexpected upstream dependency: ' . $class);
    }
}, true, true);
$_SERVER['DOCUMENT_ROOT'] = '/var/www';
$temp = sys_get_temp_dir() . '/skeeks-css-' . bin2hex(random_bytes(8));
mkdir($temp . '/assets', 0700, true);
mkdir($temp . '/css');
file_put_contents($temp . '/css/input.css', '/*! License */ a { background: url(../img/icon.png); color: #ffffff; }');
new yii\web\Application([
    'id' => 'css-test', 'basePath' => $temp, 'vendorPath' => dirname($argv[1]),
    'components' => [
        'request' => ['cookieValidationKey' => 'test', 'scriptFile' => $temp . '/index.php', 'scriptUrl' => '/index.php', 'hostInfo' => 'https://example.test', 'url' => '/test'],
        'assetManager' => ['basePath' => $temp . '/assets', 'baseUrl' => '/assets'],
    ],
]);
$component = new skeeks\yii2\assetsAuto\AssetsAutoCompressComponent(['webroot' => $temp]);
$method = new ReflectionMethod($component, '_processingCssFiles');
$method->setAccessible(true);
try {
    foreach ([false, true] as $compress) {
        $component->cssFileCompress = $compress;
        $result = $method->invoke($component, ['/css/input.css?v=1' => '<link rel="stylesheet" href="/css/input.css?v=1">']);
        $url = array_key_first($result);
        if (strpos($url, '/assets/css-compress/') !== 0) {
            throw new RuntimeException('Component fell back instead of compiling.');
        }
        $output = file_get_contents($temp . strtok($url, '?'));
        if (strpos($output, '/img/icon.png') === false || strpos($output, '#fff') === false) {
            throw new RuntimeException('Unexpected compiled CSS.');
        }
        if ($result !== $method->invoke($component, ['/css/input.css?v=1' => '<link rel="stylesheet" href="/css/input.css?v=1">'])) {
            throw new RuntimeException('Cached CSS result changed.');
        }
    }
    echo "PASS: real component, cold/warm cache, final compression on/off, upstream autoload blocked.\n";
} finally {
    yii\helpers\FileHelper::removeDirectory($temp);
}
