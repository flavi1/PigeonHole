# pigeonhole

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Build Status][ico-travis]][link-travis]
[![Coverage Status][ico-scrutinizer]][link-scrutinizer]
[![Quality Score][ico-code-quality]][link-code-quality]
[![Total Downloads][ico-downloads]][link-downloads]


Cette petite bibliothèque, inspirée par AltoRouter ne fait qu'une seule chose. Elle associe une ressource (une autre classe, un fichier, une entrée dans une base de donnée...) à un ou plusieurs chemins (URL, chemin d'accès...) grâce à des patterns.
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

## Comment ?

Voyons comment utiliser cette bibliothèque à travers quelques exemples complets.

## Un gestionnaire de pages (templates, surcharges, cache, router, et génération d'url)

Voici un premier exemple relativement "complexe" pour se faire une idée des possibilités offertes par cette bibliothèque. Qui peut le plus peut le moins.

Commençons par définir des chemins globaux 'root', 'theme', et 'www'. Ce sont des préfixes qui vont nous permettre de rendre notre projet simple à migrer.

    PigeonHole::setGlobalPath('root', '/var/www');
    PigeonHole::setGlobalPath('theme', ['/var/themes/extendable_theme', '/var/themes/child_theme']);
    PigeonHole::setGlobalPath('www', 'http://mydomain.com');

Définissons maintenant des types de chemins pour les ressources de type "page"

    PigeonHole::map('page', 'theme_path', '%theme%/[a:name].tpl');
    PigeonHole::map('page', 'src_path', '%root%/templates/[a:name].tpl');
    PigeonHole::map('page', 'cache_path', '%root%/cache/[a:name].php');
    PigeonHole::map('page', 'url', '%www%/[a:name]');

Ces types de chemins utilise le paramètre alphanumérique "name" noté [a:name] dans les patterns ci dessus. (cf tout en bas les différents types de paramètres.)

Soit une page "home". C'est à dire une ressource de type "page" dont le paramètre alphanumérique "name" est "home". On souhaite connaitre les différents chemins qui lui sont associés.

    PigeonHole::generatePaths('page', ['name' => 'home'])

Cette méthode nous renverra le tableau suivant :

    Array
    (
        [theme_path] => Array
            (
                [0] => /var/themes/extendable_theme/home.tpl
                [1] => /var/themes/child_theme/home.tpl
            )
    
        [src_path] => Array
            (
                [0] => /var/www/templates/home.tpl
            )
    
        [cache_path] => Array
            (
                [0] => /var/www/cache/home.php
            )
    
        [url] => Array
            (
                [0] => http://mydomain.com/home
            )
    
    )

Notez que extendable_theme et child_theme sont fournis dans le même ordre que nous avons définit pour le chemin global "theme". On introduit un peu de complexité : on décide que child_theme étends extendable_theme. Si child_theme ne fournis pas de page "home", on cherchera la source fournie par extendable_theme. Si ce dernier ne fournis par de source pour cette page, on utilisera le template par défaut définit par "src_path". La compilation sera stockée dans "/var/www/cache/home.php".

Ici notre ressource thème se compose d'un fichier source "/var/www/templates/home.tpl", de deux possibles surcharges de thèmes "extendable_theme" et "child_theme", d'un cache php, et d'une url.

Le code à écrire dépendra du moteur de template utilisé, et pourra combiner l'utilisation de `file_exists` et `filemtime` pour déterminer quelle fichier source sera choisi pour générer le cache (voire la documentation de ces deux fonctions php).

Bien souvent, on ne souhaitera générer que l'URL de la page seule :

    PigeonHole::generatePaths('page', ['name' => 'home'], 'url');

Inversement, j'ai l'url de la page et je souhaite déterminer de quelle page il s'agit.

    PigeonHole::resolve('http://mydomain.com/home');

Nous obtenons :

    Array
    (
        [type] => page
        [params] => Array
            (
                [name] => home
            )
    
        [path_type] => url
    )

## Un Autoloader PSR-4

C'est encore plus simple.
Soit un chemin global "vendors".

    PigeonHole::setGlobalPath('vendors', '/var/vendors');

On définit une ressource de type "class" et un type de chemin "class_path" :

    PigeonHole::map('class', 'class_path', '%vendors%/[a:vendor]/src/[a:class_name].php');

On veut le chemin du fichier php contenant la classe :

    PigeonHole::generatePaths('class', ['vendor' => 'Acme', 'class_name' => 'Wheel'])

Pour écrire le code nécessaire, on utilisera la fonction PHP `spl_autoload_register` en respectant le standard PSR-4, et le tour est joué.

## Une ressource dans une base de donnée

Nous avons un blog contenant des articles classés dans des catégories. Nous voulons une écriture d'url élégante contenant des titres lisibles et non les id des catégories et articles. Dans ce cas, utiliser les patterns et les paramètres de ressource n'est pas suffisant. Il nous faut une fonction qui transforme les id en titres, et vice versa. Cette fonction prend en second argument la variable booléenne "resolve". Lorsqu'elle est à false, le premier argument contient les paramètres de ressources (ici, l'id de l'article et de la catégorie), et la fonction est supposée retourner les paramètres utilisé par les patterns. Lorsqu'elle est à true, le premier argument contient utilisé par les patterns (ici, le titre de l'article et de la catégorie), et la fonction est supposée retourner les paramètres de la ressource.

    $routeHandler = function($opt, $resolve) {
    	if($resolve) {
    		if( $opt['category'] == 'valid' and $opt['title'] == 'exists' )
    			return [
    				'id' => 3,
    				'category_id' => 1
    			];
    	} elseif( $opt['id'] == 3 and $opt['category_id'] == 1 ) {
    		return [
    			'category' => 'my_category',
    			'title' => 'my_article'
    		];
    	}
    	return false;
    };

Définissons quelques URLs liés aux articles :

    PigeonHole::map('article', 'view', '%www%/[a:category]/[a:title]/view', $routeHandler);
    PigeonHole::map('article', 'edit', '%www%/[a:category]/[a:title]/edit', $routeHandler);
    PigeonHole::map('article', 'remove', '%www%/[a:category]/[a:title]/remove', $routeHandler);

Générons les URLs pour un article donné :

    PigeonHole::generatePaths('article', [
    	'id' => 3,
    	'category_id' => 1
    ])

Retrouvons l'article à partir d'une URL qui lui est associée.

    PigeonHole::resolve('http://mydomain.com/my_category/my_article/edit');

De façon générale, on utilisera une fonction "handler" lorsque l'on devra dissocier les paramètres utilisés dans les patterns de ceux de la ressource.

## Un serveur d'image

    PigeonHole::setGlobalPath('img_root', '/www/img');
    PigeonHole::setGlobalPath('img_www', 'http://mydomain.com/img');
    
    
    PigeonHole::map('img', 'src', '%img_root%/[a:name].[a:extension]');
    PigeonHole::map('img', 'url_small', '%img_www%/300x300/[a:name].[a:extension]');
    PigeonHole::map('img', 'url_medium', '%img_www%/600x600/[a:name].[a:extension]');
    PigeonHole::map('img', 'url_large', '%img_www%/1500x1500/[a:name].[a:extension]');


# Les différents types de paramètres

TODO, cf AltoRouter.


## Install

Via Composer

``` bash
$ composer require flavi1/pigeonhole
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
