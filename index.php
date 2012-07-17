<?

include('Stream.php');
include('Decompiler.php');

if(@$_REQUEST['content']) {
	$st = new Stream($_REQUEST['content']);

	$dc = new Decompiler($st);
	$st->setDecompiler($dc);

	try {
		do {
			$tag = $dc->cleanTag();
		} while ($tag);

		$content = $dc->getOutput();
	} catch (Exception $e) {

		$content = $_REQUEST['content'];
		$error = $st->getPos() . $e->getMessage();
	}
}

?>

<html>
	<body>
		<?= isset($error) ? $error . '<br />' : '' ?>
		<form method="POST">
			<textarea rows='40' cols='150' name="content"><?=@htmlspecialchars($content)?></textarea>
			<input type="submit" />
			<br />
			<a href="caveats.html">Maybe take a look at our caveats</a>
		</form>
	</body>
</html>
