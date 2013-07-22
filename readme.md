PHP Shrink
==========

Library for shrinking of PHP code.

Usage
-----

``` php
<?php
	$shrinker = new Cz\PhpShrink;
	
	// add files
	$shrinker->addFile('myfile.php');
	$shrinker->addFile('libs.php');
	
	// or add PHP code
	$shrinker->addFile('<?php echo "hello!";');
	
	// get shrinked code
	$code = $shrinker->getOutput();
```


Installation
------------

[Download a latest package](https://github.com/czproject/phpshrink/releases) or use [Composer](http://getcomposer.org/):

```
composer require czproject/phpshrink
```

PhpShrink requires PHP 5.3.0 or later.


------------------------------

Based on [class](https://github.com/nette/build-tools/blob/master/tasks/minify.php#L63) by [David Grudl](http://davidgrudl.com/).

David Grudl, http://davidgrudl.com/
<br>Jan Pecha, http://janpecha.iunas.cz/

