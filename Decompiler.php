<?php

class Decompiler {
	
	private $version = "Converted from Smarty-2.6.13 to PHP 5.3 by
		SmartyTranslate v0.2.3 (created for this project)";
	
	private $stream;
	private $stack = array('');
	private $foreachstack = array();
	private $widgetstack = array();
	private $OPERATORS = array(	'eq' => '==',
								'neq' => '!=',
								'==' => '==',
								'!=' => '!=',
								'+' => '+',
								'-' => '-',
								'/' => '/',
								'*' => '*',
								'>=' => '>=',
								'>' => '>',
								'<=' => '<=',
								'<' => '<',
								'&&' => '&&',
								'||' => '||',
								'AND' => '&&',
								'OR' => '||',
								'XOR' => '^',
								'^' => '^',
			);
	
	const NO_ENDFOREACH = 'NOENDFOREACH';
	
	public function getOperators() {
		return $this->OPERATORS;
	}
	
	/**
	 * Upon {foreachelse}, pop this stack to get the variable to existence check
	 * and then push self::NO_ENDFOREACH to note that {endforeach} is instead
	 * ending the existence if statement. Alternatively, if you get an item
	 * when you {endforeach} that's fine, just end the foreach and ignore the
	 * value on the stack. 
	 *
	 * @return string 
	 */
	public function popForeachStack() {
		return array_pop($this->foreachstack);
	}
	
	
	/**
	 * When opening a foreach, add its item to the stack. Then if it ends in a
	 * foreachelse, you can go back to add existence checking. If you pop this
	 * stack on a foreachelse, push self::NO_ENDFOREACH so that {endforeach} 
	 * becomes a <? endif ?>
	 *
	 * @param string $item to be existence checked upon foreachelse
	 */
	public function pushForeachStack($item) {
		$this->foreachstack[] = $item;
	}
	
	public function __construct(Stream $stream) {
		$this->stream = $stream;
		$this->output('<?php /* '. $this->version . ' on ' . date('M-d-y') . ' */ ?>' . PHP_EOL . PHP_EOL);
	}
	
	/**
	 * Load a smarty call from the stream and decide whether its a variable echo
	 * or its a function or its a comment. Cleans the tag appropriately.
	 * 
	 * @return string|false
	 *  
	 */
	function cleanTag() {
		$tag = $this->stream->getNextSmartyCall();
		$this->stream->moveChar();
		$this->stream->eatWhiteSpace();
		
		if(!$tag) return false;
		
		if($this->stream->getChar() === '$') {
			$return = '<?php echo ' . $this->cleanVariable() . '; ?>';
		} else if ($this->stream->getChar() === '*') {
			$return = $this->cleanComment();
		} else {
			$return = $this->cleanFunction($tag);
		}
		$this->output($return);
		
		$tail = $this->stream->getChar();
		
		return $return;
	}
	
	/**
	 * This outputs our code on a stack. Anywhere we may conditionally go back
	 * to before other smarty tags, we can wedge it between slices of the stack.
	 *
	 * @param string $out 
	 */
	function output($out) {
		$this->stack[count($this->stack) - 1] .= $out;
	}
	
	/**
	 * This is the master output getter for our compiler
	 * @return type 
	 */
	function getOutput() {
		return implode($this->stack);
	}
	
	function openStack() {
		$this->stack[] = '';
	}
	
	/**
	 * Pulls the text in the top of the stack down one level. Use $wrap to put
	 * text in between the two values on the stack.
	 * 
	 * @param string $wrap 
	 */
	function closeStack($wrap = '') {
		$append = $wrap . array_pop($this->stack);
		$this->stack[count($this->stack) - 1] .= $append;
	}
	
	/**
	 * Gets the call invoked in a tag and responds appropriately
	 * 
	 * @param type $tag
	 * @return string
	 */
	function cleanFunction($tag) {
		switch($this->stream->previewNextWord($tag)) { 
		//TODO: previewNextWord is deprecated
			case 'ldelim':
				$this->stream->moveToClosingTag();
				return '{';
				
			case 'rdelim':
				$this->stream->moveToClosingTag();
				return '}';
				
			case 'php':
				$this->stream->moveToClosingTag();
				return PHP_EOL .'<?php /* This may need adapting to work without smarty */' . PHP_EOL;
				
			case '/php':
				$this->stream->moveToClosingTag();
				return PHP_EOL . '/* This may need adapting to work without smarty */ ?> ' . PHP_EOL;
				
			case 'if':
				$this->stream->moveToWhitespace();
				return '<?php if(' . $this->cleanExpression() . '): ?>';
				
			case '/if':
				$this->stream->moveToClosingTag();
				return '<?php endif; ?>';
				
			case 'else':
				$this->stream->moveToClosingTag();
				return '<?php else: ?>';
				
			case 'elif':
				$this->stream->moveToWhitespace();
				return '<?php if(' . $this->cleanExpression() . '): ?>';
				
			case 'foreach':
				$this->stream->moveToWhitespace();
				$data = $this->getParamVals();
				$this->expectParams($data, array('from', 'item'), array('name', 'key'));
				$this->pushForeachStack($data['from']);
				$this->openStack();
				$this->stream->moveToClosingTag();
				return '<?php foreach(' . $data['from'] . ' as ' . (isset($data['key']) ? '$' . $this->unquote($data['key']) . ' => ' : '') . '$' . $this->unquote($data['item']) . '): ?>';

			case 'foreachelse':
				$this->stream->moveToClosingTag();
				return $this->cleanForeachelse();
				
			case 'endforeach':
			case '/foreach':
				$this->stream->moveToClosingTag();
				if($this->popForeachStack() === self::NO_ENDFOREACH) {
					return '<?php endif; ?>';
				} else {
					$this->closeStack();
					return '<?php endforeach; ?>';
				}
				
			case 'assign':
				$this->stream->moveToWhitespace();
				$data = $this->getParamVals();
				$this->expectParams($data, array('var', 'value'));
				return '<?php $' . $this->unquote($data['var']) . ' = ' . $data['value'] . '; ?>';
				
			case 'cycle':
				$this->stream->moveToWhitespace();
				$data = $this->getParamVals();
				$this->expectParams($data, array('values'));
				return '<?php echo Stools::Cycle(' . $data['values'] . ') ?>';
				
			case 'capture':
				$this->stream->moveToWhitespace();
				$data = $this->getParamVals();
				$this->expectParams($data, array('name'));
				$return = '<?php /* begin capture ' . $data['name'] . ' */ ' . PHP_EOL;
				$this->widgetstack[] = $this->unquote($data['name']);
				return $return . 'ob_start(); ?>' . PHP_EOL;
				
			case '/capture':
				$this->stream->moveToWhitespace();
				$return = '<?php $_' . array_pop($this->widgetstack) . ' = ob_get_clean(); ?>' . PHP_EOL;
				return $return;
				
			case 'widget':
				$this->stream->moveToWhitespace();
				$data = $this->getParamVals();
				if(!isset($data['type'])) throw new Exception("Widgets need a type");
				$this->widgetstack[] = $this->unquote($data['type']);
				$vars = $this->printAnonymousDataArray($data, array('type'));
				return '<?php STools::widget_start(' . $data['type'] . ', '  . $vars . ') ?>';
				
			case '/widget':
				$this->stream->moveToWhitespace();
				return '<?php STools::widget_end(); ?>';
				
			case 'include':
				$this->stream->moveToWhitespace();
				$data = $this->getParamVals();
				$url = $this->quote(@$data['tpl'] ? @$data['tpl'] : @$data['file']);
				if(!$url) throw new Exception('No url for include');$count = 0;
				$url = str_replace(".tpl", ".php", $url, &$count); 
				if($count > 1) throw new Exception("Couldn't clean .tpl reference");
				$vars = $this->printAnonymousDataArray($data, array('tpl', 'file'));
				return '<?php $this->load->view(' . $url . ($vars === 'null' ? '' : ', ' . $vars) . ') ?>';
				
			case 'include_js':
				$this->stream->moveToWhitespace();
				$data = $this->getParamVals();
				$this->expectParams($data, array(), array('tpl', 'file'));
				$url = $this->quote(@$data['tpl'] ? @$data['tpl'] : @$data['file']);
				if(!$url) throw new Exception('No url for include');
				return '<?php echo STools::IncludeJS(' . $url . ') ?>';
				
			case 'include_css':
				$this->stream->moveToWhitespace();
				$data = $this->getParamVals();
				$this->expectParams($data, array(), array('tpl', 'file', 'media'));
				$url = $this->quote(@$data['tpl'] ? @$data['tpl'] : @$data['file']);
				if(!$url) throw new Exception('No url for include');
				return '<?php echo STools::IncludeCSS(' . $url . (@$data['media'] ? ', ' . $data['media'] : '') . ') ?>';
				
			default:
				throw new Exception('Unrecognized smarty tag: ' . $tag);
				break;
		}
	}
	
	/**
	 * Find all param=argument within curly braces, return the values in an array
	 * keyed by param.
	 * 
	 * @return string[compiled string] 
	 */
	function getParamVals() {
		$return = array();
		do {
			$this->stream->eatWhiteSpace();
			$key = $this->cleanParam();
			if($this->stream->isAlpha()) {
				$return[$key] = $this->stream->getNextWord();
				$this->stream->moveChar();
			} else {
				$return[$key] = $this->cleanArgument();
			}
		} while($this->stream->getChar() != '}');
		
		return $return;
	}
	
	/**
	 * get the PHP code in a string format, that will create this data array.
	 * 
	 * @param array $data
	 * @param string[] $exclude 
	 * @return string|null
	 */
	function printDataArray($data, $exclude) {
		$vars = '';
		if(isset($data)) {
			foreach($data as $key => $val) {
				if(in_array($key, $exclude)) continue;
				$vars .= '	\'' . $key . '\' => ' . $val . ',' . PHP_EOL;
			}
		}
		
		return $vars ? PHP_EOL . '$_vdata = array(' . PHP_EOL . $vars . PHP_EOL . ");" . PHP_EOL : null;
	}
	
	/**
	 * get the PHP code in a string format, that will create this data array.
	 * 
	 * @param array $data
	 * @param string[] $exclude 
	 * @return string|null
	 */
	function printAnonymousDataArray($data, $exclude) {
		$vars = '';
		if(isset($data)) {
			foreach($data as $key => $val) {
				if(in_array($key, $exclude)) continue;
				$vars .= '\'' . $key . '\' => ' . $val . ',';
			}
		}
		
		return $vars ? 'array(' . $vars . ")" : 'null';
	}
	
	/**
	 * Send in an array of keys and values as $data, the list of keys you require,
	 * and the list of keys that are optional. Any param keys that aren't in $req
	 * or $opt will throw exceptions, and any missing $req will also throw an
	 * exception
	 *  
	 * @param $data the data array of params/values
	 * @param $req the params you require
	 * @param $opt optional params
	 */
	function expectParams($data, $req, $opt = array()) {
		foreach($req as $key) {
			if(!isset($data[$key])) throw new Exception("Expected a '" . $key . "' parameter."); 
		}
		foreach($data as $param => $unused) {
			if(!in_array($param, $req) && !in_array($param, $opt))
				throw new Exception("Unexpected '" . $param . "' parameter."); 
		}
	}
	
	/**
	 * If we find a foreachelse, we have to pop our foreach stack and existence
	 * check it before the foreach was started. Then we end the foreach and push
	 * onto the foreach stack an indicator that this has happened.
	 *
	 * @return string 
	 */
	function cleanForeachelse() {
		$item = $this->popForeachStack();
		$this->pushForeachStack(self::NO_ENDFOREACH);
		$this->closeStack('<?php if(@' . $item . '): ?>');
		return '<?php endforeach; else: ?>';
	}
	
	/**
	 * An argument can be a number, string, or variable.
	 * 
	 * @return string 
	 */
	function cleanArgument() {
		if(ctype_digit($this->stream->getChar())) {
			return $this->cleanNumber();
		}
		
		$this->stream->expectChar('\'"$');
		
		if($this->stream->getChar() === '$') {
			return $this->cleanVariable();
		} else {
			return $this->cleanString();
		}
	}
	
	/**
	 * This is what I am the most unsure about. Therefore I've opted to do as
	 * little as possible. Operators can be +, -, &&, >, I'll accept anything.
	 * 
	 * BNF:
	 * [%][!]<argument>[%] { <operator> [%][!]<argument>[%] }
	 * 
	 * @return string
	 *  
	 */
	function cleanExpression() {
		$return = '';
		$was_arg = false;
		do {
			if($this->stream->getChar() == ')' || $this->stream->getChar() == '(') {
				$return .= $this->stream->getChar();
				$this->stream->moveChar();
			}
			if(!$was_arg) {
				if($this->stream->getChar() === '!') {
					$return .= '!';
					$this->stream->moveChar();
					continue;
				}
				if($this->stream->getChar() === '%') {
					$return .= $this->cleanClassConstant();
					$was_arg = true;
					continue;
				}
				$return .= $this->cleanArgument();
				if($this->stream->getChar() == ')' || $this->stream->getChar() == '(') {
					$return .= $this->stream->getChar();
					$this->stream->moveChar();
				}
				//$this->stream->eatWhiteSpace();
				$was_arg = true;
			} else {
			$this->stream->eatWhiteSpace();
				$return .= $this->cleanOperator();
				$was_arg = false;
			}
		} while($this->stream->getChar() != '}');
		
		if(!$was_arg) throw new Exception('Expression ended in an operator.');
		
		return $return;
	}
	
	function cleanOperator() {
		foreach($this->getOperators() as $match => $operator) {
			//echo '[' . $match . '|' . $this->stream->readAhead(strlen($match),0) . ']';
			if($this->stream->readAhead(strlen($match),0) == $match) {
				$i = 0;
				while($i < strlen($match) || ctype_space($this->stream->getChar())) {
					$this->stream->moveChar();
					$i++;
				}
				return ' ' . $operator . ' ';
			}
		}
		
		throw new Exception ('Didn\'t get a recognized operator ' . $this->stream->readAhead(4,0));
	}
	
	function cleanClassConstant() {
		$return = '';
		
		do {
			if($this->stream->isAlpha() || $this->stream->getChar() == ':') {
				$return .= $this->stream->getChar();
			}
			
			$this->stream->moveChar();
			
			if(	$this->stream->isWhitespace() ||
				$this->stream->getChar() == '}' ||
				$this->stream->getChar() == Stream::EOF ||
				$this->stream->getChar() == '`' ) {
				break;
			}
		} while(true);
		
		return $return;
	}
	
	/**
	 * Turn a smarty $variable.index->method()|function:args into PHP equivalent
	 * function($variable['index']->method(),args)
	 * 
	 * the $met_* variables are 0 for not yet matched, 1 for matched, 2 for matched
	 * and closed properly -- so we know to close $array['index and check for
	 * $obj->isthisapropertyoramethod
	 * 
	 * BNF
	 * <variable> ::= '$' { alph } [ { '.' { alph } } | '->' { alph } [ '()' ] ] [ '|' { alph } { ':' { <variable> | <string> } } ]
	 *  
	 * @return string
	 */
	function cleanVariable() {
		$met_dollarsign = false;	// $
		$met_text = false;			// $var as well as the text for indexes, funcs, methods, and properties
		$met_indexdot = false;		// $var.
		$met_dashrocket = false;	// $var->method()
		$met_funcpipe = false;		// $var|
	
		$elements = array(
			'main' => '',
			'funcname' => '',
			'method' => '',
			'index' => '',
			'methodparam' => '',
			'methodparens' => false,
			'arguments' => array(),
		);
	
		do {
			if($this->stream->readAhead(8) == '$smarty.') {
				return $this->cleanSmartyVar();
			}
			
			// Must begin with a $ to be a variable.
			if(!$met_dollarsign) {
				if($this->stream->expectChar('$')) {
					$met_dollarsign = true;
				}
			} else
			
			// Must have text for a base name, funcname, etc
			if(!$met_text) {
				if($this->stream->expectAlph()) {
					$text = $this->stream->getNextWord();
					$met_text = true;
					if($met_dashrocket == 1) {
						$elements['method'] = $text;
						if($this->stream->readCharAhead(1) == '(') {
							$elements['methodparens'] = true;
							$this->stream->moveChar();
							$this->stream->moveChar();
							if($this->stream->getChar() !== ')') {
								$elements['methodparam'] = $this->cleanArgument();
							}
							$this->stream->expectChar(')');
						}
						$met_dashrocket = 2;
					} else if($met_indexdot == 1) {
						$elements['index'] = $text;
						$met_indexdot = 2;
					} else if($met_funcpipe == 1) {
						$elements['funcname'] = $text;
						$met_funcpipe = 2;
					} else {
						$elements['main'] = $text;
					}
				}
			} else
			
			// TODO: differentiate between full variables, and ones called within
			// args
			// Can have an index dot, dashrocket, or a func pipe
			if(!$met_indexdot && !$met_dashrocket && !$met_funcpipe) {
				// the loop would terminate if we weren't expecting another element
				if($this->stream->expectChar('|.-')) {
					switch($this->stream->getChar()) {
						case '-':
							$this->stream->moveChar();
							$this->stream->expectChar('>');
							$met_dashrocket = true;
							$met_text = false;
							break;
						case '.':
							$met_indexdot = true;
							$met_text = false;
							break;
						case '|':
							$met_funcpipe = true;
							$met_text = false;
							break;
					}
				}
			} else if(!$met_funcpipe) {
				// the loop would terminate if we weren't expecting another element
				$this->stream->expectChar('|');
				$met_funcpipe = true;
				$met_text = false;
			} else if($met_funcpipe) {
				// the loop would terminate if we weren't expecting another element
				$this->stream->expectChar(':');
				$this->stream->moveChar();
				
				//use recursion for args
				$elements['arguments'][] = $this->cleanArgument();
				$this->stream->moveBack(); 
				// the while loop pushes us forward after cleanArgument, and then
				// our while loop increments again. Big eww, but it beats
				// adding crazy logic to our loop.
			}

			$this->stream->moveChar();
			
			if(	$this->stream->isWhitespace() ||
				$this->stream->getChar() == '}' ||
				$this->stream->getChar() == Stream::EOF ||
				$this->stream->getChar() == '`' ||
				$this->stream->getChar() == ')' ) {
				break;
			}
		} while(true);

		if(strtolower($elements['main']) == 'user') {
			$is_user = true;
			$return = 'Application::getUser()';
		} else {
			$is_user = false;
			$return = '$' . $elements['main'];
		}

		if($elements['index']) $return .= '[\'' . $elements['index'] . '\']';
		if($elements['method']) {
			 $return .= '->' . $elements['method'];
			if($elements['methodparens']) $return .= '(' . $elements['methodparam'] . ')';
			elseif(!$is_user) $return = '@' . $return;
		} elseif(!$is_user) {
			$return = '@' . $return;
		}

		if($is_user) {
			if($elements['index']) throw new Exception('Why is user an array?');
			if($elements['method'] == 'getType') $return = 'Application::getUserType()';
			if($elements['method'] == 'getUserID') $return = 'Application::getUserID()';
		}

		//Clean up smarty's functions
		switch ($elements['funcname']) {
			case '':
				return $return;

			case 'cat':
				if(count($elements['arguments']) > 1) throw new Exception("Only expected one argument to cat");
				return $return . ' . ' . $elements['arguments'][0];

			case 'replace':
				return 'str_replace(' . $elements['arguments'][0] . ', ' . $elements['arguments']['1'] . ', ' . $return . ')';

			case 'is_a':
				throw new Exception($elements['funcname'] . ' has not been implemented. We can either do it manually, add to the compiler, or write a Stool.');
			
			case 'f':
			case 'func':
				echo "test func called, ";
				return $elements['funcname'] . '(' . $return . (!empty($elements['arguments'][0]) ? ', ' . join($elements['arguments'], ', ') : '') . ')';

			default:
				if(!function_exists($elements['funcname'])) throw new Exception($elements['funcname'] . ' probably isn\'t a PHP function. Add to compiler and/or write a Stool.');
				return $elements['funcname'] . '(' . $return . (!empty($elements['arguments'][0]) ? ', ' . join($elements['arguments'], ', ') : '') . ')';
		}
		
		return $return;
		
	}
	
	public function cleanSmartyVar() {
		for($i=0;$i<8;$i++) $this->stream->moveChar();
		$sub = $this->stream->getNextWord();
		$this->stream->moveChar();
		switch($sub) {
			case 'foreach':
				throw new Exception('$smarty.foreach is not supported');
				
			case 'const':
				$this->stream->moveChar();
				$return = $this->stream->getNextWord();
				$this->stream->moveChar();
				return $return;
				
			case 'session':
				$this->stream->moveChar();
				$key = $this->stream->getNextWord();
				$this->stream->moveChar();
				switch($key) {
					case 'user':
						return 'Application::getUser()';
					case 'user_id':
						return 'Application::getUserID()';
					case 'user_type':
						return 'Application::getUserType()';
					default:
						return 'Application::getSessionVar(\'' . $key . '\')';
				}
				
			case 'request':
				$this->stream->moveChar();
				$key = $this->stream->getNextWord();
				$this->stream->moveChar();
				return '@$_REQUEST[\'' . $key . '\']';
				
			case 'now':
				return 'date()';
				
			case 'capture':
				$this->stream->moveChar();
				$key = $this->stream->getNextWord();
				$this->stream->moveChar();
				return '@$_' . $key;
				
			default:
				throw new Exception('Unknown smarty variable, $smarty.' . $sub);
		}
	}
	
	/**
	 * Strings will be delimited consistently, and will concatenate variables
	 * nested inside the string "using `$backticks` if they're there."
	 * 
	 * Uses $opened to prevent this translation error
	 * '`$s`string`$s`' => '' . $s . 'string' . $s . ''
	 * 
	 * @return string
	 */
	function cleanString() {
		$this->stream->expectChar('\'"');
		$delim = $this->stream->getChar();
		$return = '';
		$opened = false;
		while($this->stream->moveChar() != $delim) {
			
			if($this->stream->getChar() == '`') {
				$this->stream->moveChar();
				if($opened) $return .= $delim . ' . ';
				$return .= $this->cleanVariable();
				$this->stream->expectChar('`');
				$opened = false;
				//$this->stream->moveChar();
			} else {
				if(!$opened) {
					if($return !== '') $return .= ' . ';
					$return .= $delim;
					$opened = true;
				}
				$return .= $this->stream->getChar();
			}
			
		}
		
		$this->stream->moveChar();
		
		if($return == '') return $delim . $delim;
		
		return $opened ? $return . $delim : $return;
	}
	
	/**
	 * At the moment this only takes integers. But smarty might allow floats
	 * and stuff.
	 * 
	 * @return string
	 * 
	 */
	function cleanNumber() {
		$return = '';
		
		do {
			$return .= $this->stream->getChar();
			$this->stream->moveChar();
		} while(ctype_digit($this->stream->getChar()));
		
		return $return;
	}
	
	/**
	 * Takes the inside of a comment and encloses it in a php comment block in php tags.
	 *
	 * @return string
	 * @throws Exception 
	 */
	function cleanComment() {
		$this->stream->moveChar();
		$this->stream->moveChar();
		
		$return = '<?php /*';
		while($this->stream->getChar() . $this->stream->readCharAhead(1) != '*}') {
			$return .= $this->stream->getChar();
			
			if($this->stream->getChar() == Stream::EOF) {
				throw new Exception('EOF reached during comment');
			}
			
			$this->stream->moveChar();
		}
		
		$this->stream->moveChar();
		
		return $return . '*/ ?>';
	}
	
	/**
	 * A param is the name I chose for {call param=argument}
	 * 
	 * @return string
	 *  
	 */
	function cleanParam() {
		$this->stream->expectAlph();
		$return = '';
		
		while($this->stream->isAlpha()) {
			$return .= $this->stream->getChar();
			$this->stream->moveChar();
		}
		
		$this->stream->expectChar('=');
		$this->stream->moveChar();
		return $return;
	}
	
	/**
	 * Alot of params accept arguments within quotes or not, this gets just the
	 * contents of a quoted string, otherwise the contents go back untouched.
	 *
	 * @param type $string
	 * @return string 
	 */
	function unquote($string) {
		if(strpos($string,'\'') === 0 || strpos($string,'"') === 0) {
			return substr($string,1, strlen($string) - 2);
		}
		
		return $string;
	}
	
	/**
	 * Alot of params accept arguments within quotes or not, this forces them
	 * to be quoted
	 *
	 * @param type $string
	 * @return string 
	 */
	function quote($string) {
		
		return '\'' . $this->unquote($string) . '\'';
	}
}
