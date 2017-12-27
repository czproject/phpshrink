PHP Shrink
==========

Library for shrinking of PHP code.


Installation
------------

[Download a latest package](https://github.com/czproject/phpshrink/releases) or use [Composer](http://getcomposer.org/):

```
composer require czproject/phpshrink
```

PhpShrink requires PHP 5.3.0 or later.


Usage
-----

``` php
<?php
	$shrinker = new Cz\PhpShrink;

	// add files
	$shrinker->addFile('myfile.php');
	$shrinker->addFile('libs.php');

	// add array of files
	$shrinker->addFiles(array(
		'functions.php',
		'exceptions.php',
	));

	// or add PHP code
	$shrinker->addContent('<?php echo "hello!";');

	// get shrinked code
	$code = $shrinker->getOutput();
```

------------------------------

Based on [class](https://github.com/nette/build-tools/blob/master/tasks/minify.php#L63) by [David Grudl](https://davidgrudl.com/).

David Grudl, https://davidgrudl.com/
<br>Jan Pecha, https://www.janpecha.cz/
