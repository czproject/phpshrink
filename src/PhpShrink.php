<?php
	namespace Cz;


	/**
	 * @link	https://github.com/nette/build-tools/blob/master/tasks/minify.php#L63
	 */
	class PhpShrink
	{
		/** @var  array|NULL  first PhpDoc-comment token */
		public $firstComment = NULL;

		/** @var  bool */
		public $useNamespaces = FALSE;

		/** @var  string */
		private $output = '';

		/** @var  array  [expr => TRUE] */
		private $uses = array();

		/** @var  bool */
		private $inPHP;

		/** @var  string|NULL */
		private $namespace;

		/** @var  array  [real-filepath => TRUE] */
		private $files;

		/** @var  callback[] */
		private $filters;



		/**
		 * @param	string
		 * @return	self
		 */
		public function addFile($file)
		{
			$this->files[realpath($file)] = TRUE;
			$content = file_get_contents($file);
			$this->addContent($content, $file);

			return $this;
		}



		/**
		 * @param	string[]
		 * @return	self
		 */
		public function addFiles(array $files)
		{
			foreach($files as $file)
			{
				$this->addFile($file);
			}

			return $this;
		}



		/**
		 * @return	array|NULL  [filepath => TRUE]
		 */
		public function getFiles()
		{
			return $this->files;
		}



		/**
		 * @param	string
		 * @param	string|NULL  filepath (for __DIR__, dirname(__FILE__) & filters)
		 * @return	self
		 */
		public function addContent($content, $file = NULL)
		{
			$dir = dirname($file);
			$content = $this->applyFilters($content, $file);
			$tokens = token_get_all($content);

			if ($this->useNamespaces) { // find namespace
				$hasNamespace = FALSE;
				foreach ($tokens as $num => $token)	{
					if ($token[0] === T_NAMESPACE) {
						$hasNamespace = TRUE;
						break;
					}
				}
				if (!$hasNamespace) {
					$tokens = token_get_all(preg_replace('#<\?php#A', "<?php\nnamespace;", $content)); // . '}');
				}
			}

			if ($this->inPHP) {
				if (is_array($tokens[0]) && $tokens[0][0] === T_OPEN_TAG) {
					// trick to eliminate ?><?php
					unset($tokens[0]);
				} else {
					$this->output .= '?>';
					$this->inPHP = FALSE;
				}
			}


			$set = '!"#$&\'()*+,-./:;<=>?@[\]^`{|}';
			$space = $pending = FALSE;

			reset($tokens);
			while (list($num, $token) = each($tokens))
			{
				if (is_array($token)) {
					$name = $token[0];
					$token = $token[1];
				} else {
					$name = NULL;
				}

				if ($name === T_CLASS || $name === T_INTERFACE) {
					for ($i = $num + 1; @$tokens[$i][0] !== T_STRING; $i++);

				} elseif ($name === T_COMMENT || $name === T_WHITESPACE) {
					if ($pending) {
						$expr .= ' ';
					} else {
						$space = TRUE;
					}
					continue;

				} elseif ($name === T_PUBLIC && ($tokens[$num + 2][0] === T_FUNCTION || $tokens[$num + 4][0] === T_FUNCTION)) {
					next($tokens);
					continue;

				} elseif ($name === T_DOC_COMMENT) {
					if (!$this->firstComment) {
						$this->firstComment = $token;
						$this->output .= $token . "\n";
						$space = TRUE;
						continue;

					} elseif (preg_match('# @[mA-Z]#', $token)) { // phpDoc annotations leave unchanged

					} else {
						$space = TRUE;
						continue;
					}

				} elseif ($name === T_INCLUDE || $name === T_INCLUDE_ONCE || $name === T_REQUIRE || $name === T_REQUIRE_ONCE) {
					$pending = $name;
					$reqToken = $token;
					$expr = '';
					continue;

				} elseif ($name === T_NAMESPACE || $name === T_USE) {
					$pending = $name;
					$expr = '';
					continue;

				} elseif ($pending && ($name === T_CLOSE_TAG || ($name === NULL && ($token === ';' || $token === '{' || $token === ',') || ($pending === T_USE && $token === '(')))) { // end of special
					$expr = trim($expr);
					if ($pending === T_NAMESPACE) {
						if ($this->namespace !== $expr) {
							if ($this->namespace !== NULL) {
								$this->output .= "}";
							}
							$this->output .= "namespace $expr{";
							$this->uses = array();
							$this->namespace = $expr;
						}

					} elseif ($pending === T_USE) {
						if ($token === '(') {
							$this->output .= "use(";

						} elseif (!isset($this->uses[$expr])) {
							$this->uses[$expr] = TRUE;
							$this->output .= "use\n$expr;";
						}

					} else { // T_REQUIRE_ONCE, T_REQUIRE, T_INCLUDE, T_INCLUDE_ONCE
						$newFile = strtr($expr, array(
							'__DIR__' => "'" . addcslashes($dir, '\\\'') . "'",
							'dirname(__FILE__)' => "'" . addcslashes($dir, '\\\'') . "'",
						));
						$newFile = @eval('return ' . $newFile . ';');

						if ($newFile && realpath($newFile)) {
							$oldNamespace = $this->namespace;

							if ($pending !== T_REQUIRE_ONCE || !isset($this->files[realpath($newFile)])) {
								$this->addFile($newFile);
							}

							if (!$this->inPHP && $name !== T_CLOSE_TAG) {
								$this->output .= '<?php ';
								$this->inPHP = TRUE;
							}

							if ($this->namespace !== $oldNamespace) {
								if ($this->namespace !== NULL) {
									$this->output .= "}";
								}
								$this->namespace = $oldNamespace;
								$this->output .= "namespace $oldNamespace{";
								if ($this->uses && $oldNamespace) {
									$this->output .= "use\n" . implode(',', array_keys($this->uses)) . ";";
								}
							}
						} else {
							$this->output .= " $reqToken $expr;";
						}
					}
					if ($token !== ',') {
						$pending = FALSE;
					}
					$expr = '';
					continue;

				} elseif ($name === T_OPEN_TAG || $name === T_OPEN_TAG_WITH_ECHO) { // <?php <? <% <?=  <%=
					$this->inPHP = TRUE;

				} elseif ($name === T_CLOSE_TAG) { // ? > %>
					if ($num === count($token-1)) continue; // eliminate last close tag
					$this->inPHP = FALSE;

				} elseif ($token === ')' && substr($this->output, -1) === ',') {  // array(... ,)
					$this->output = substr($this->output, 0, -1);

				} elseif ($pending) {
					$expr .= $token;
					continue;
				}

				if ($space) {
					if (strpos($set, substr($this->output, -1)) === FALSE && strpos($set, $token{0}) === FALSE) {
						$this->output .= "\n";
					}
					$space = FALSE;
				}

				$this->output .= $token;
			}

			return $this;
		}



		/**
		 * @return	string
		 */
		public function getOutput()
		{
			if ($this->namespace !== NULL) {
				$this->output .= "}";
				$this->namespace = NULL;
			}
			return $this->output;
		}



		/**
		 * @param	callback
		 * @return	self
		 */
		public function addFilter($callback)
		{
			$this->filters[] = $callback;
			return $this;
		}



		/**
		 * @param	string
		 * @param	string|NULL
		 * @return	string
		 */
		private function applyFilters($content, $file = NULL)
		{
			foreach((array) $this->filters as $filter)
			{
				$content = $filter($content, $file);
			}

			return $content;
		}



		/**
		 * @param	string
		 * @return	string|NULL
		 */
		public static function filterPdo($content, $file = NULL)
		{
			// special handling for Connection.php && Statement.php
			$content = preg_replace('#class \S+ extends \\\\?PDO.+#s', "if (class_exists('PDO')){ $0 }", $content);
		}
	}

