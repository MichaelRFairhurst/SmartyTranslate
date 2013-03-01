<?

register_shutdown_function(function() {
	$error = error_get_last();
	if($error) {
		$msg = $error['message'] . ' on ' . $error['file'] . ':' . $error['line'];
		mail('mfairhurst@mediprodirect.com, cole@manifestwebdesign.com', 'error in smarty parser', $msg);
	}
}); 

include('Stream.php');
include('Decompiler.php');

$line = 0;
$content = null;

if(isset($_REQUEST['content'])) {
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
		$line = $st->getLine();
	}
}

?>

<html>
	<body>
		<?= isset($error) ? $error . '<br />' : '' ?>
		<form method="POST">
			<textarea rows='40' cols='150' id='content' name="content"><?=@htmlspecialchars($content)?></textarea>
			<input type="submit" />
			<br />
			<a href="caveats.html">Maybe take a look at our caveats</a>
		</form>
		<script>
				text = document.getElementById('content');
				lines = text.value.split('\n');
				for(i = 0; i < <?=$line?>; i++) {
					if(i > 0) {
						text.selectionStart += lines[i - 1].length + 1;
					} else {
						text.selectionStart = 0;
					}
					text.selectionEnd = text.selectionStart + lines[i].length;
				}
		</script>

	</body>
</html>
