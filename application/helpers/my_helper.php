<?php

function pr($array, $title = '') {
	if (!empty($title)) echo '<h5>'.$title.'</h5>';
	echo PHP_EOL.'<pre>';
	print_r($array);
	echo '</pre><hr>';
}

function toAbsolute($link) {
	if (preg_match('#^https?:\/\/#i', $link) || $link == '') return $link;
	else return base_url('assets/'.$link);
}

function toRelative($link) {
	if (preg_match('#^https?:\/\/#i', $link) || $link == '') return $link;
	else return '../../assets/'.$link;
}

function printFlashMessages() {
	if (!empty($_SESSION['errors'])) {
		foreach($_SESSION['errors'] as $name => $message) { ?>
			<div class="alert alert-danger" role="alert">
				<button type="button" class="close" data-dismiss="alert" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
				<b><?= ucfirst($name) ?>&nbsp;: </b><?= $message ?>
			</div>
		<?php }
		unset($_SESSION['errors']);
	}
	
	if (!empty($_SESSION['warnings'])) {
		foreach($_SESSION['warnings'] as $name => $message) { ?>
			<div class="alert alert-warning" role="alert">
				<button type="button" class="close" data-dismiss="alert" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
				<b><?= ucfirst($name) ?>&nbsp;: </b><?= $message ?>
			</div>
		<?php }
		unset($_SESSION['warnings']);
	}
	
	if (!empty($_SESSION['infos'])) {
		foreach($_SESSION['infos'] as $name => $message) { ?>
			<div class="alert alert-primary" role="alert">
				<button type="button" class="close" data-dismiss="alert" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
				<b><?= ucfirst($name) ?>&nbsp;: </b><?= $message ?>
			</div>
		<?php }
		unset($_SESSION['infos']);
	}

	if (!empty($_SESSION['success'])) {
		foreach($_SESSION['success'] as $name => $message) { ?>
			<div class="alert alert-success" role="alert">
				<button type="button" class="close" data-dismiss="alert" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
				<b><?= ucfirst($name) ?>&nbsp;: </b><?= $message ?>
			</div>
		<?php }
		unset($_SESSION['success']);
	}
}

function unwrap_array($array = null, $depth = -1) {
	if (empty($array)) return null;

	if (!is_array($array) || ($depth == 0)) return [$array];

	$ret = array();

	foreach ($array as $key => $value) {
		$ret = array_merge($ret, unwrap_array($value, $depth-1));
	}

	return array_unique($ret);
}

function array_map_recursive($f, $xs) {
    $out = [];
    foreach ($xs as $k => $x) {
        $out[$k] = (is_array($x)) ? array_map_recursive($f, $x) : $f($x);
    }
    return $out;
}

function scandir_recursive($dir) {
	if (empty($dir)) return null;
	if (is_file($dir)) return $dir;

	if (substr($dir, -1) == '/') $dir = substr($dir, 0, -1);
	$sd = array_diff(scandir($dir), ['.', '..']);

	$ret = array();

	foreach ($sd as $file) {
		if (is_file($file)) $ret[] = $file;
		else $ret[$file] = scandir_recursive($dir.'/'.$file);
	}

	return $ret;
}

function convertDate($date = null, $format_in = '', $format_out = '') {
	if (empty($date) || empty($format_in) || empty($format_out)) return null;
	else return date_format(date_create_from_format($format_in, $date), $format_out);
}

function frenchDate($date = null, $format_in = '') {
	if (!empty($ndate = convertDate($date, $format_in, 'l j F Y'))) {
		$replacements = array(
			'#January#i' => 'janvier',
			'#February#i' => 'février',
			'#March#i' => 'mars',
			'#April#i' => 'avril',
			'#May#i' => 'mai',
			'#June#i' => 'juin',
			'#July#i' => 'juillet',
			'#August#i' => 'août',
			'#September#i' => 'septembre',
			'#October#i' => 'octobre',
			'#November#i' => 'novembre',
			'#December#i' => 'décembre',

			'# 1#i' => ' 1er',

			'#Monday#i' => 'lundi',
			'#Tuesday#i' => 'mardi',
			'#Wednesday#i' => 'mercredi',
			'#Thursday#i' => 'jeudi',
			'#Friday#i' => 'vendredi',
			'#Saturday#i' => 'samedi',
			'#Sunday#i' => 'dimanche',
		);

		return preg_replace(array_keys($replacements), array_values($replacements), $ndate);
	} else return null;
}

// Formats $num into french format.
// If $signed is set to true, it always puts the sign before the number, 
// So -1 returns -1,00 and 1 returns +1,00
function frenchNumber($num = 0, $signed = false) {
	return (($signed && $num>0)?'+':'').number_format($num, 2, ',', ' ');
}

function sqlPrepare($db = null, $sql = '') {
	if (empty($db) || empty($sql)) return false;

	return $db->conn_id->prepare($sql);
}

function execPreparedSql(PDOStatement $stmt = null, $binds = null, $errMsg = '') {
	if (empty($stmt) || empty($binds) || !is_array($binds)) return false;

	if ($stmt->execute($binds)) return true;
	else {
		if (!empty($errMsg)) log_message('error', $errMsg);
		log_message('debug', 'Erreur '.$stmt->errorCode().': '.$stmt->errorInfo()[2]);
		return false;
	}

}

function execSqlFile($db = null, $filepath = '') {
	if (empty($db) || empty($filepath)) return false;

	if (!is_file($filepath)) {
		log_message('error', 'execSqlFile: '.$filepath.' is not a file');
		return false;
	} else {
		try {
			$sql = file_get_contents($filepath);

			$comments = array(
				'#\/\*.*\*\/#U',        // Les blocs de commentaires /* */
				'#--.*\r?\n#U',         // Les commentaires de fin de ligne --
				'#\#.*\r?\n#U'         // Les commentaires de fin de ligne #
			);

			$sql = preg_replace($comments, '', $sql);
			$sql = preg_replace('#;[\s;]*;#', ';', $sql);

			$db->conn_id->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$db->conn_id->query($sql);

			return true;
		} catch (Exception $e) {
			pr($e);die;
			log_message('error', $e->getMessage());
			log_message('debug', 'Erreur '.$e->errorCode().': '.$e->errorInfo()[2]);
			return false;
		}
	}
}

function setHead500() {
	header('HTTP/1.1 500 Internal Server Error');
}

function setHeadJSON() {
	header('Content-Type: application/json');
}

// Returns $array without the keys in $keys
function arrayWithoutKey($array = null, $keys = null) {
	if (empty($array) || empty($keys)) return $array;

	if (!is_array($keys)) $keys = [$keys];

	return array_diff_key($array, array_flip($keys));
}