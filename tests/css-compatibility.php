<?php
// Standalone golden checks; optional differential checks against mrclay/minify 3.0.14.
$_SERVER['DOCUMENT_ROOT'] = '/var/www';
spl_autoload_register(function ($class) {
    $prefix = 'skeeks\\yii2\\assetsAuto\\vendor\\mrclay\\';
    if (strpos($class, $prefix) === 0) {
        require __DIR__ . '/../src/vendor/mrclay/' . substr($class, strlen($prefix)) . '.php';
    }
});

use skeeks\yii2\assetsAuto\vendor\mrclay\CSS;

$cases = [
    ['a { color: #ffffff; }', [], 'a{color:#fff}'],
    ['@charset "UTF-8"; a { color: red; }', [], 'a{color:red}'],
    ['/*! License */ a { color: red; } /* discard */', [], "\n/*! License */\na{color:red}"],
    ['/*! License */ a { color: red; }', ['preserveComments' => false], 'a{color:red}'],
    ['a { background: url(../img/a.png?v=1#x); }', ['prependRelativePath' => '/assets/css/'], 'a{background:url(/assets/img/a.png?v=1#x)}'],
    ['@import "theme.css"; a { background: url(/img/a.png); }', ['prependRelativePath' => '/assets/'], '@import "/assets/theme.css";a{background:url(/img/a.png)}'],
    ['a { background: url(https://cdn.test/a.png); mask: url(#mask); }', ['prependRelativePath' => '/assets/'], 'a{background:url(https://cdn.test/a.png);mask:url(#mask)}'],
    ['a { background: url(//cdn.test/a.png); }', ['prependRelativePath' => '/assets/'], 'a{background:url(//cdn.test/a.png)}'],
    ['a { background: url(data:image/png;base64,AAAA); }', ['prependRelativePath' => '/assets/'], 'a{background:url(data:image/png;base64,AAAA)}'],
    ['a { color: red; }', ['compress' => false], 'a { color: red; }'],
    ['a{background:url(../img/a.png)}', ['currentDir' => '/var/www/css', 'docRoot' => '/var/www'], 'a{background:url(/img/a.png)}'],
    ['a{background:url(icon.png)}', ['currentDir' => '/srv/static/css', 'docRoot' => '/var/www', 'symlinks' => ['//static' => '/srv/static']], 'a{background:url(/static/css/icon.png)}'],
];
// Intentional divergences from upstream 3.0.14 (not used in differential mode)
$fixes = [
    // Spaces around "+" in calc() must survive when a selector list follows
    [".x {\n gap: 1px;\n padding-top: calc(var(--h) + 48px); margin: 0;\n}\n.a, .b { color: red; }", [], '.x{gap:1px;padding-top:calc(var(--h) + 48px);margin:0}.a,.b{color:red}'],
    ['.c{height:calc(2.25rem + 2px)}.d,.e{color:blue}', [], '.c{height:calc(2.25rem + 2px)}.d,.e{color:blue}'],
    ['@media (max-width: 991px) { .f { width: calc(50% + 30px) } .g > a + b, .h ~ i { color: red } }', [], '@media (max-width: 991px){.f{width:calc(50% + 30px)}.g>a+b,.h~i{color:red}}'],
];
$count = 0;
foreach (array_merge($cases, $fixes) as $case) {
    $actual = CSS::minify($case[0], $case[1]);
    if ($actual !== $case[2]) {
        throw new RuntimeException('Golden case ' . $count . ': ' . var_export($actual, true));
    }
    ++$count;
}
if (class_exists('Minify_CSS', false)) {
    throw new RuntimeException('Golden checks unexpectedly loaded upstream.');
}
echo "PASS: $count standalone CSS cases without upstream.\n";

if (isset($argv[1])) {
    require $argv[1];
    if (!class_exists('Minify_CSS')) {
        throw new RuntimeException('Differential mode requires original mrclay/minify 3.0.14.');
    }
    $inputs = array_column($cases, 0);
    $inputs[] = ':root { --gap: 12px; } a { width: calc(100% - var(--gap)); font-family: "Open Sans", Arial; }';
    $inputs[] = '@import url("../other.css") screen; @font-face { src: url("../font.woff2?v=2#font"); }';
    $inputs[] = 'a >/**/ b { color: red; } a { color/**/:/**/blue; }';
    $inputs[] = '@charset "UTF-8"; /*! One */ a { color:red; } /*! Two */ b { color: blue; }';
    foreach (array_slice($argv, 2) as $path) {
        $input = file_get_contents($path);
        if ($input === false) {
            throw new RuntimeException('Cannot read stylesheet: ' . $path);
        }
        $inputs[] = $input;
    }
    $compared = 0;
    foreach ($inputs as $input) {
        foreach ([[], ['prependRelativePath' => '/assets/css/'], ['prependRelativePath' => 'https://cdn.example.com/css/'], ['currentDir' => '/var/www/css', 'docRoot' => '/var/www'], ['currentDir' => '/srv/static/css', 'docRoot' => '/var/www', 'symlinks' => ['//static' => '/srv/static']], ['compress' => false], ['preserveComments' => false], ['removeCharsets' => false]] as $options) {
            if (CSS::minify($input, $options) !== \Minify_CSS::minify($input, $options)) {
                throw new RuntimeException('Upstream mismatch: comparison ' . $compared);
            }
            ++$compared;
        }
    }
    echo "PASS: $compared byte-for-byte upstream comparisons.\n";
}
