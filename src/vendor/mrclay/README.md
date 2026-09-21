# CSS-код из mrclay/minify

Внутренняя копия только используемой CSS-обработки из **mrclay/minify 3.0.14**,
commit `dc02cdfba70737828c947fde33bc3f8235a742f5`:
https://github.com/mrclay/minify/tree/dc02cdfba70737828c947fde33bc3f8235a742f5

## Почему убрана обязательная зависимость

Для объединения CSS нашему компоненту требовалась небольшая часть большого
пакета. Его обязательная зависимость `marcusschwarz/lesserphp` помечена Composer
как abandoned, хотя компиляция LESS здесь не используется. Upstream также
предупреждает в README, что проект регулярно не поддерживается и не рекомендует
его для современных проектов. Abandoned само по себе не означает уязвимость.

На этом этапе сохраняем проверенное поведение существующих сайтов, а не меняем
CSS-движок. Копирование не устраняет ограничения старого regex-алгоритма и не
делает его современным CSS-парсером. Поддержка этой локальной копии теперь наша.

## Состав и лицензия

| Локальный файл | Исходный файл upstream |
| --- | --- |
| CSS.php | lib/Minify/CSS.php |
| Compressor.php | lib/Minify/CSS/Compressor.php |
| UriRewriter.php | lib/Minify/CSS/UriRewriter.php |
| CommentPreserver.php | lib/Minify/CommentPreserver.php |

Сохранены авторские комментарии. Оригинальная BSD-3-Clause лицензия целиком
находится в [LICENSE.txt](LICENSE.txt), контрольные суммы исходных файлов до
адаптации — в [UPSTREAM-SHA256.txt](UPSTREAM-SHA256.txt).

Единственные адаптации: namespace `skeeks\yii2\assetsAuto\vendor\mrclay`, имена
классов и ссылки между ними (включая строковые callbacks), комментарий об
источнике. Алгоритмы и параметры не изменены. Старые глобальные классы не
объявляются; установленный сторонним потребителем mrclay/minify не конфликтует.
HTML, JavaScript, LESS и HTTP-сервер upstream не копировались.

## Совместимость и обновление сайтов

`AssetsAutoCompressComponent` использует локальный CSS-код. `skeeks/cssmin`
продолжает выполнять прежнее дополнительное сжатие. JS и HTML по умолчанию
не менялись. Необязательный `MrclayHtmlCompressor` оставлен: использующие его
сайты должны явно установить `mrclay/minify` либо выбрать `TylerHtmlCompressor`.
При отсутствии зависимости выдаётся понятный `InvalidConfigException`.

После релиза обновить пакет и связанные зависимости через Composer, проверить
изменения composer.lock и выполнить composer audit. Загрузка PHP-файлов в vendor
сама по себе не удаляет старые зависимости из composer.lock или установленного
vendor. Если mrclay/minify или lesserphp нужны другим пакетам, Composer их оставит;
проверить это можно через `composer why mrclay/minify` и
`composer why marcusschwarz/lesserphp`. Не использовать фиктивные replace/provide.

## Проверки

`php tests/css-compatibility.php` проверяет результат без Composer/upstream.
Для сравнения с оригиналом 3.0.14:
`php tests/css-compatibility.php /path/to/vendor/autoload.php [stylesheet.css ...]`.
Интеграционная проверка: `php tests/css-integration.php /path/to/vendor/autoload.php` — настоящий компонент, отдельный временный каталог, холодный и готовый кеш, блокировка загрузки upstream. Первый запуск должен проходить и в окружении, где mrclay/minify не установлен.
При изменениях обязательно проверять URL изображений/шрифтов, @import, data URI,
SVG, charset, комментарии и обе ветки переписывания путей.

## План следующего этапа

Рассмотреть [matthiasmullie/minify](https://github.com/matthiasmullie/minify).
Сейчас он не подключён. Переход потребует отдельного сравнения реальных CSS,
обработки @import, URL, автоматического встраивания ресурсов и результатов
рендеринга. Не совмещать замену движка с этим переносом.
