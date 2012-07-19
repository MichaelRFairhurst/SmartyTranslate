<?

include('DecompilerTest.php');

// Misc test declarations
$tests = array(	new DecompilerTest('Number', '1', '1', ' EOF'),
				new DecompilerTest('Number', '1234', '1234', ' EOF'),
				new DecompilerTest('Param', 'a=', 'a', '&'), // any delim
				new DecompilerTest('Param', 'aahoet=', 'aahoet', '&'), // any delim
				new DecompilerTest('Comment','{*aoseuh*}','<? /*aoseuh*/ ?>', '}'), // tags end on }
				new DecompilerTest('SmartyVar','$smarty.now','date()', ' '),
				new DecompilerTest('SmartyVar','$smarty.session.user','Application::getUser()', ' '),
				new DecompilerTest('SmartyVar','$smarty.session.user_id','Application::getUserID()', ' '),
				new DecompilerTest('SmartyVar','$smarty.session.user_type','Application::getUserType()', ' '),
				new DecompilerTest('SmartyVar','$smarty.session.junk','Application::getSessionVar(\'junk\')', ' '),
				new DecompilerTest('SmartyVar','$smarty.request.junk','@$_REQUEST[\'junk\']', ' '),
				new DecompilerTest('SmartyVar','$smarty.capture.junk','@$_junk', '&'),
				new DecompilerTest('SmartyVar','$smarty.const.JUNK','JUNK', '&'),
				);

// cleanString test generation
$string_bases = array(	'"string"' => '"string"',
			'\'string\'' => '\'string\'',
			'"`$test`string"' => '@$test . "string"', 
			'\'`$test`string\'' => '@$test . \'string\'',
			'"string`$test`"' => '"string" . @$test', 
			'\'string`$test`\'' => '\'string\' . @$test',
			'"string`$test`string"' => '"string" . @$test . "string"',
			'\'string`$test`string\'' => '\'string\' . @$test . \'string\'',
		);

foreach ($string_bases as $in => $out) {
			//On strings the delim doesn't matter,
			// as long as it ends pointing to it.
	$tests[] = new DecompilerTest('String', $in, $out, '&');
}

// cleanVariable test generation
$function_bases = array('$test' => '@$test',
                        '$test.index' => '@$test[\'index\']',
                        '$test.i' => '@$test[\'i\']',
                        '$test->m' => '@$test->m',
                        '$test->meem' => '@$test->meem',
                        '$test->meem()' => '$test->meem()',
			'$user' => 'Application::getUser()',
			'$user->getUserID()' => 'Application::getUserID()',
			'$user->getType()' => 'Application::getUserType()');
$function_vars = array(	'"string"' => '"string"',
			'"t"' => '"t"',
			'1234' => '1234',
			'1' => '1',
			'$test' => '@$test',
			'$t' => '@$t',
			);
$variables = array();

foreach($function_bases as $base_in => $base_expect) {
	$variables[$base_in] = $base_expect;
	$variables[$base_in . '|f'] = 'f(' . $base_expect . ')';
	$variables[$base_in . '|func'] = 'func(' . $base_expect . ')';
	
	foreach($function_vars as $first_in => $first_expect) {
		$variables[$base_in . '|f:' . $first_in] = 'f(' . $base_expect . ', ' . $first_expect . ')';
		$variables[$base_in . '|func:' . $first_in] = 'func(' . $base_expect . ', ' . $first_expect . ')';
		
		if(strpos($first_in, '$') === 0) continue;

		foreach ($function_vars as $second_in => $second_expect) {
			$variables[$base_in . '|f:' . $first_in . ':' . $second_in] = 'f(' . $base_expect . ', ' . $first_expect . ', ' . $second_expect . ')';
			$variables[$base_in . '|func:' . $first_in . ':' . $second_in] = 'func(' . $base_expect . ', ' . $first_expect . ', ' . $second_expect . ')';

		}
	}
}	
foreach($variables as $in => $out) {
	$tests[] = new DecompilerTest('Variable', $in, $out, ' `}EOF');
}

//Arguments for use later.
$arguments = array_merge($function_bases, $string_bases);
$arguments[1] = 1;
$arguments[1234] = 1234;

// cleanOperator test generation
$operators = array(	'eq' => '==',
			'neq' => '!=',
			'==' => '==',
			'!=' => '!=',
			'+' => '+',
			'-' => '-',
			'/' => '/',
			'*' => '*',
			'>' => '>',
			'>=' => '>=',
			'<' => '<',
			'<=' => '<=',
			'&&' => '&&',
			'||' => '||',
			'AND' => '&&',
			'OR' => '||',
			'XOR' => '^',
			'^' => '^',
			);

foreach($operators as $in => $out) {
	$tests[] = new DecompilerTest('Operator', $in, ' ' . $out . ' ', 'a'); //any nonspace should do
	
	foreach($arguments as $first_in => $first_out) {

		foreach($arguments as $second_in => $second_out) {
			$test_in = $first_in . ' ' . $in . ' ' . $second_in;
			$test_out = $first_out . ' ' . $out . ' ' . $second_out;
			$tests[] = new DecompilerTest('Expression', $test_in, $test_out, '}');
		}
	}
}

//Assigns
foreach($arguments as $arg => $out) {
	$tests[] = new DecompilerTest('Function','assign var=a value=' . $arg, '<? $a=' . $out . '; ?>', '}');
}

// Parent test generation
foreach ($tests as $test) {
	$tests = array_merge($tests, $test->createParentTests());
}

// Run
$successes = 0;
$errors = array();
foreach ($tests as $test) {
	$res = $test->run();
	if($res) {
		$successes++;
		echo $test->getReport();
	} else {
		$errors[] = $test->getReport();
	}
}

// Report
echo implode('',$errors);
echo $successes . ' of ' . count($tests) . ' succeeed ' . PHP_EOL;
