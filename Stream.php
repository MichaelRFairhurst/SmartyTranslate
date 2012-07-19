<?php

class Stream {
	
	private $pos = 0;
	private $line = 1;
	private $pointer;
	private $data;
	private $len;
	private $literal = false;
	private $comment = false;
	private $decompiler;
	const EOF = 'EOF';
	
	/**
	 * This takes a string and splits it into an array of characters, and takes
	 * their count. That is the data that this stream controls access to.
	 * 
	 * @param string $string 
	 */
	function __construct($string) {
		$this->len = strlen($string);
		$this->data = str_split($string);
		$this->pointer = -1;
	}
	
	function __destruct() {
		if($this->isLiteral()) echo ('EOF reached before closing literal tag.'); 
		////TODO: make this an error! stupid php not having a stack during destructors
	}
	
	function setDecompiler($decompiler) {
		$this->decompiler = $decompiler;
	}
	
	/**
	 * Use this for describing errors.
	 * 
	 * @return string 
	 */
	function getPos() {
		return 'At line ' . $this->line . ', pos ' . $this->pos . ': ';
	}

	function getLine() {
		return $this->line;
	}
	
	/**
	 * Are we at the end of the stream/file?
	 * 
	 * @param string $pos
	 * @return bool 
	 */
	function atEOF($pos = null) {
		if($pos === null) $pos = $this->pointer;
		return $pos >= $this->len;
	}
	
	/**
	 * The stream acts as preprocessor, it takes care of {literal} tags without
	 * bothering the compiler. Returns whether or not the stream is literal. 
	 * 
	 * @return bool 
	 */
	function isLiteral() {
		return $this->literal;
	}
	
	/**
	 * The stream acts as preprocessor, it takes care of {literal} tags without
	 * bothering the compiler. This changes the literal state. 
	 * 
	 * @param bool 
	 */
	function setLiteral($val) {
		$this->literal = (bool) $val;
	}
	
	/**
	 * The stream determines tag beginnings and endings, therefore it must know
	 * if the current tag is a comment. If it is, it expects *} to end the tag. 
	 * 
	 * @return bool 
	 */
	function isComment() {
		return $this->comment;
	}
	
	/**
	 * The stream determines tag beginnings and endings, therefore it must know
	 * if the current tag is a comment. If it is, it expects *} to end the tag. 
	 * 
	 * @param bool 
	 */
	function setComment($val) {
		$this->comment = (bool) $val;
	}
	
	/**
	 * Move forward one char.
	 * 
	 * @return char
	 */
	function moveChar() {
		if($this->atEOF(++$this->pointer)) return self::EOF;
		if($this->data[$this->pointer] == PHP_EOL) {
			$this->line++;
			$this->pos = 0;
		} else {
			$this->pos++;
		} 
		return $this->data[$this->pointer];
	}

	/**
	 * Try not to use, this really goes against the grain of compilers.
	 */
	function moveBack() {
		$this->pos--;
		$this->pointer--;
	}
	
	/**
	 * Get the current char.
	 * 
	 * @return char 
	 */
	function getChar() {
		if($this->atEOF()) return self::EOF;
		return @$this->data[$this->pointer];
	}
	
	/**
	 * Preview a string from upstream, starting on $offset and grabbing $length
	 * characters down.
	 * 
	 * @param type $length
	 * @param type $offset	defaults to 1 (the first upstream char)
	 * @return string 
	 */
	function readAhead($length,$offset = 0) {
		$ret = '';
		$i = 0;
		while($i < $length) {
			$index = $this->pointer + $offset + $i;
			if(!$this->atEOF($index)) $ret .= $this->data[$index];
			$i++;
		}
		
		return $ret;
	}
	
	/**
	 * Preview an upstream char, $offset chars from the stream pointer.
	 * 
	 * @param type $offset
	 * @return type 
	 */
	function readCharAhead($offset) {
		$index = $this->pointer + $offset;
		return $this->atEOF($index) ? self::EOF : $this->data[$index];
	}
	
	/**
	 * This processor handles non-smarty output. This is the text that will
	 * not show up in our PHP tags
	 * 
	 * @param string $out 
	 */
	function outputHTML($out) {
		$this->decompiler->output($out);
	}
	
	/**
	 * Our parser doesn't have an appetite for characters or symbols, but it
	 * will gobble up tabs, spaces, and newlines on command.
	 * 
	 * Ignores the current character, assumes you don't want it.
	 * 
	 * @return boolean	if there was whitespace to be eaten.
	 *  
	 */
	function eatWhiteSpace() {
		$hungry = (bool) ctype_space($this->getChar());
		$got_food = false;
		while($hungry) {
			switch($this->moveChar()) {
				case '\n':
				case '\t':
				case ' ':
					$got_food = true;
					break;
				default:
					$hungry = false;
			}
		}
		
		return $got_food;
	}
	
	/**
	 * Strip whitespace at the beginning of the string, and grab consecutive
	 * alphabetic characters, possibly led with a '/'
	 * 
	 * @deprecated
	 * @param type $string 
	 */
	function previewNextWord($string) {
		$string = trim($string);
		$char_array = str_split($string);
		$ret = '';
		foreach($char_array as $c) {
			if(ctype_alpha($c) || ($ret === '' && $c === '/') || $c == '_') 
			{
				$ret .= $c;
			} else {
				return $ret;
			}
		}
		return $ret; //TODO: is this bad?
	}
	
	/**
	 * read & move pointer with characters, 
	 * 
	 * @param type $allow_underscore
	 * @return type 
	 */
	function getNextWord($allow_underscore = true) {
		$return = '';
		
		while(ctype_space($this->getChar())) $this->moveChar();
		
		while(true) {
			$return .= $this->getChar();

			if(!$this->isAlpha($this->readCharAhead(1))) return $return;
			
			$this->moveChar();
		}
		
		return $return;
	}
	
	/**
	 * Move the pointer to the next open brace, outputting the html it finds
	 * along the way.
	 * 
	 * @param bool $throw_error = true		Throw an error if none exists.
	 * @return boolean 
	 */
	function findNextOpenTag($throw_error = true) {
		if($this->getChar() === '{') return true;
		
		while($this->moveChar() != self::EOF) {
			 if($this->getChar() === '{') return true;
			 $this->outputHTML($this->getChar());
		}
		
		return false;
	}
	
	/**
	 * Preview the upstream characters, stopping at a closing brace, or
	 * false if EOF is reached. 
	 *
	 * @return string|boolean 
	 */
	function readToClosingTag() {
		$ret = '';
		$i = 0;
		
		while(true) {
			$c = $this->readCharAhead(++$i);
			
			if($c === self::EOF)					throw new Exception("EOF reached before closing smarty tag.");
			if(!$this->isComment() && $c === '}')	return $ret;
			if($c . $this->readCharAhead($i + 1) === '*}')		return $ret;
			
			$ret .= $c;
			
		}
		
		return false;
	}
	
	/**
	 * Also moves past the closing tag. 
	 */
	function moveToClosingTag() {
		while($this->getChar() != '}') $this->moveChar(); 
		//$this->moveChar();
	}
	
	/**
	 * Also moves past the whitespace. 
	 */
	function moveToWhitespace() {
		while(!ctype_space($this->getChar())) $this->moveChar(); 
		$this->eatWhiteSpace();
	}
	
	/**
	 * Preview the content of the the next smarty tag in the stream, or false
	 * if there are no more tags. Tracks literal/non literal using recursion.
	 * 
	 * @return string|boolean 
	 */
	function getNextSmartyCall() {
		if(!$this->findNextOpenTag()) return false;
		if($this->readCharAhead(1) === '*' && !$this->isLiteral()) $this->setComment(true);
		
		$inner = $this->readToClosingTag();
		
		if($this->isLiteral() && trim($inner) == '/literal') {
			$this->setLiteral(false);
			$this->moveToClosingTag();
			$inner = $this->getNextSmartyCall();
		} else if ($this->isLiteral()) {
			$this->outputHTML($this->getChar());
			$this->moveChar();
			$this->outputHTML($this->getChar());
			$inner = $this->getNextSmartyCall();
		} else if(!$this->isLiteral() && trim($inner) == 'literal') {
			$this->setLiteral(true);
			$this->moveToClosingTag();
			$inner = $this->getNextSmartyCall();
		}
		
		$this->setComment(false); // The stream no longer cares about comments.
		return $inner ? $inner : false;
	}

	/**
	 * Throws a compile error if the pointed char in the stream doesn't match
	 * any of the chars in $chars. 
	 * 
	 * @param string $chars		A string of allowed characters such as '!.%*='
	 * @return boolean 
	 */
	function expectChar($chars) {
		$return = false;
		$matches = str_split($chars);
		foreach($matches as $char) {
			if($this->getChar() == $char) $return = true;
		}
		
		if(!$return) throw new Exception('Expected ' . $chars . ', got ' . $this->getChar());
		
		return true;
	}
	
	/**
	 * Throw a compile error if the pointed char in the stream isn't an 
	 * alphabetic character.
	 * 
	 * @return boolean 
	 */
	function expectAlph() {
		if(!ctype_alpha($this->getChar()) & $this->getChar() != '_') {
			throw new Exception('Expected alphabetic character, got ' . $this->getChar());
			return false;
		}
		return true;
	}
	
	/**
	 * Like expectAlpha(), but no exception. Just saves typing.
	 * 
	 * @return bool
	 */
	function isAlpha($char = null) {
		if($char == null) $char = $this->getChar();
		return (ctype_alnum($char) || $char === '_') && $char != self::EOF;
	}
	
	/**
	 * Like isAlpha(). Just saves typing.
	 * 
	 * @return bool
	 */
	function isWhitespace() {
		return ctype_space($this->getChar());
	}
}
