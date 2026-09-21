<?php
require $argv[1] ?? '/app/vendor/autoload.php';
require_once dirname($argv[1] ?? '/app/vendor/autoload.php').'/yiisoft/yii2/Yii.php';
require __DIR__.'/../src/vendor/HtmlCompressor.php';
use skeeks\yii2\assetsAuto\vendor\HtmlCompressor;
$cases = [
    ['', [], ''],
    ["  <div>  a   b </div>  \n\n", [], '<div> a b </div>'],
    ["0\n<div>zero</div>", [], "0\n<div>zero</div>"],
    ["<div>\r\n  hello\r\n</div>", [], "<div>\nhello\n</div>"],
    ["<pre>\n  a    b\n\n  c\n</pre>\n <p>x</p>", [], "<pre>\n  a    b\n\n  c\n</pre>\n<p>x</p>"],
    ["<textarea>\n  a   b\n</textarea>\n <p>x</p>", [], "<textarea>\n  a   b\n</textarea>\n<p>x</p>"],
    ["  <pre> a   b </pre>\n <p>x</p>", [], "<pre> a   b </pre>\n<p>x</p>"],
    ["<p>a</p>\n<!-- comment\n comment -->\n<!>\n<p>b</p>", ['no-comments'=>true], "<p>a</p>\n\n\n<p>b</p>"],
    ["<div>\n  <span> x </span>\n</div>", ['extra'=>true], '<div><span> x </span></div>'],
    ["<div>\n  <span> x </span>\n</div>", ['x'=>false], '<div><span> x </span></div>'],
    ["<!-- retained -->\n<p>Текст</p>", ['no-comments'=>false], "<!-- retained -->\n<p>Текст</p>"],
    ["<p>x</p><!-- removed -->", ['c'=>false], '<p>x</p>'],
];
foreach ($cases as $i=>[$html,$options,$expected]) {
    $actual=HtmlCompressor::compress($html,$options);
    if ($actual!==$expected) throw new RuntimeException('Case '.$i.': '.var_export($actual,true));
}
ob_start();
$result=HtmlCompressor::compress("  <p>x</p>  ", ['stats'=>true]);
$stats=ob_get_clean();
if ($result!==false || $stats!=="Original Size: 13\nCompressed Size: 8\nSavings: 38.46%\n") throw new RuntimeException('Statistics changed: '.$stats);
$html=str_repeat("    <article><a href=\"/product\">Товар</a> <span>1000</span></article>\n",20000);
$start=microtime(true);
$output=HtmlCompressor::compress($html);
if ($output!==trim(str_repeat("<article><a href=\"/product\">Товар</a> <span>1000</span></article>\n",20000))) throw new RuntimeException('Large document changed');
printf("OK: 14 compressor checks; %d bytes / 20000 lines in %.2f ms\n",strlen($html),(microtime(true)-$start)*1000);
