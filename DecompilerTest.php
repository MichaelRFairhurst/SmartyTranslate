<?

/**
	* This is easy to test without PHPUnit, so I've created my own test suite/framework to save typing.
 */

include('Stream.php');
include('Decompiler.php');

class DecompilerTest {
	private $method;
	private $in;
	private $out;
	private $end_pointer;
	private $report = '';

	function __construct($m, $i, $o, $e) {
		$this->method = $m;
		$this->in = $i;
		$this->out = $o;
		$this->end_pointer = $e;
	}

	/**
	 * Creates a stream with this tests input, appended by this tests possible	
	 * delimiters. For each one it runs Decompiler->clean$method() and ensures
	 * the stream is pointing at the delimiter when its done. Then reports each
	 * of these subtests results, and returns whether or not they all passed.
	 *
 	 * @return bool whether or not all the tests passed.
	 */
	function run() {
		$return = true;
		foreach($this->splitDelimiters() as $delim) {
			if($delim == 'EOF') $delim = '';
			$st = new Stream($this->in . $delim);
			$st->moveChar();
			$dc = new Decompiler($st);
			$st->setDecompiler($dc);
			try {
				$method = 'clean' . $this->method;
				$res = $dc->$method(); 
			} catch(Exception $e) {
				$res = $e->getMessage();
			}
			if($res != $this->out) {
				$this->report($res);
				$return &= false;
			} else if(strpos($this->end_pointer,$st->getChar()) === false) {
				$this->report($res . ' and ended on char ' . $st->getChar());
				$return &= false;
			} else {
				$this->report('.');
				$return &= true;
			}
		}
		return $return;
	}

	/**
	 * Appends this tests report property with a new line describing input and output and
	 * where the pointer ended up. Send in '.' on success.
	 *
	 * @param $result The output of the test/where the pointer ended, or '.' on success.
	 */
	function report($result) {
		$this->report .= 'Testing ' . $this->method . '(' . $this->in . ') ';
		if($result != '.') $this->report .= 'gave result ';
		$this->report .= $result . PHP_EOL;
	}

	function getReport() {
		return $this->report;
	}

	/**
	 * Split up delimiter string into an array. It stops on EOF, returning EOF as a delimiter
	 *
	 * @return array
	 */
	function splitDelimiters() {
		$return = array();
		$split = str_split($this->end_pointer);
		foreach($split as $key => $val) {
			if(substr($this->end_pointer,$key,3) == 'EOF') {
				$return[] = 'EOF';
				return $return;
			} else {
				$return[] = $val;
			}
		}
		return $return;
	}

	/**
	 * Based on method name, generate all other tests that should have the same
	 * input and output.
	 *
	 * @return DecompilerTest[]
	 */
	function createParentTests() {
		$return = array();
		switch($this->method) {
			case 'Number':
			case 'String':
			case 'Variable':
				$return[] = $this->createMethodTest('Argument');
				break;
			case 'SmartyVar':
				$return[] = $this->createMethodTest('Variable');
				break;
		}
		return $return;
	}

	/**
	 * Clone this test, but with a specified method.
	 *
	 * @param $method string
	 * @return DecompilerTest
	 */
	function createMethodTest($method) {
		return new DecompilerTest($method, $this->in, $this->out, $this->end_pointer);
	}
}

