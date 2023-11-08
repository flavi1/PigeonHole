# pigeonhole

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Build Status][ico-travis]][link-travis]
[![Coverage Status][ico-scrutinizer]][link-scrutinizer]
[![Quality Score][ico-code-quality]][link-code-quality]
[![Total Downloads][ico-downloads]][link-downloads]

**Note:** Replace ```Flavien Guillon``` ```flavi1``` ```flavieng.com``` ```flavien.guillon@gmail.com``` ```flavi1``` ```pigeonhole``` ```A consistant and simple way to organize your classes, singletons, and other ressources. Can be used as a router.``` with their correct values in [README.md](README.md), [CHANGELOG.md](CHANGELOG.md), [CONTRIBUTING.md](CONTRIBUTING.md), [LICENSE.md](LICENSE.md) and [composer.json](composer.json) files, then delete this line. You can run `$ php prefill.php` in the command line to make all replacements at once. Delete the file prefill.php as well.

This is where your description should go. Try and limit it to a paragraph or two, and maybe throw in a mention of what
PSRs you support to avoid any confusion with users and contributors.

Cette petite classe, inspirée par AltoRouter ne fait qu'une seule chose. Elle associe à une ressource (une autre classe, un fichier, une entrée dans une base de donnée...) à un ou plusieurs chemins (URL, chemin d'accès...) grâce à des patterns.
Une ressource est définie par un ensemble de paramètres (c'est à vous de les définir ex : `[type => article, id => 3]`) et peut être liée à plusieurs types de chemins (ex: `url_view, url_edit, cache_file_path`...).

On peut retrouver une ressource (ses paramètres) à l'aide d'un de ses chemins, on peut aussi générer le ou les chemins associé à une ressource.

## Pourquoi faire ?

Les cas d'usages sont multiples.

 - Réaliser un router/générateur d'URL.
 - Organiser des templates, leur éventuel surcharge par un thème, ainsi que leur fichier cache.
 - Organiser un serveur d'images en utilisant les types de chemins comme des tailles.
 - Convertir de vielles URLs et/ou gérer leur redirection.
 - Écrire un autoload PSR-0 ou PSR-4.
 - Mettre en place votre propre framework PHP en utilisant les classes de votre choix et en gérant vous même la manière dont sont organisés vos fichiers. (approche modulaire, surcharges, caches, templates...)



## Structure

If any of the following are applicable to your project, then the directory structure should follow industry best practices by being named the following.

```
bin/        
build/
docs/
config/
src/
tests/
vendor/
```


## Install

Via Composer

``` bash
$ composer require flavi1/pigeonhole
```

## Usage

``` php
$skeleton = new League\Skeleton();
echo $skeleton->echoPhrase('Hello, League!');
```

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Testing

``` bash
$ composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please email flavien.guillon@gmail.com instead of using the issue tracker.

## Credits

- [Flavien Guillon][link-author]
- [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/flavi1/pigeonhole.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/flavi1/pigeonhole/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/flavi1/pigeonhole.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/flavi1/pigeonhole.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/flavi1/pigeonhole.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/flavi1/pigeonhole
[link-travis]: https://travis-ci.org/flavi1/pigeonhole
[link-scrutinizer]: https://scrutinizer-ci.com/g/flavi1/pigeonhole/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/flavi1/pigeonhole
[link-downloads]: https://packagist.org/packages/flavi1/pigeonhole
[link-author]: https://github.com/flavi1
[link-contributors]: ../../contributors
