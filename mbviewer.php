<?php

if (PHP_SAPI == 'cli') {
	$argv = $_SERVER['argv'];

	if (count($argv) !== 3) {
		printf("Usage: %s ADDRESS:PORT MBTILES_FILE\n", $argv[0]);
		exit(1);
	}

	passthru(sprintf('MBTILES_FILE=%s php -S %s %s', escapeshellarg($argv[2]), escapeshellarg($argv[1]), escapeshellarg(__FILE__)));
	exit;
}

if (!getenv('MBTILES_FILE')) {
	die('Fichier invalide');
}

$file = getenv('MBTILES_FILE');
$filename = basename($file);
$db = new \SQLite3($file, \SQLITE3_OPEN_READONLY);

if (preg_match('!^/(\d+)/(\d+)/(\d+)$!', $_SERVER['REQUEST_URI'], $match))
{
	$z = (int) $match[1];
	$x = (int) $match[2];
	$y = (int) $match[3];

	// Flip Y
	$y_real = pow(2, $z) - 1 - $y;

	$rowid = $db->querySingle(sprintf('SELECT rowid FROM tiles
		WHERE zoom_level = %d AND tile_column = %d AND tile_row = %d;', $z, $x, $y_real));

	if (!$rowid)
	{
		header('HTTP/1.1 404 Not Found', true, 404);
		printf("The requested tile can not be found: z=%d x=%d y=%d", $z, $x, $y_real);
		exit;
	}

	$format = $db->querySingle('SELECT value FROM metadata WHERE name = \'format\';');
	$format = strtolower($format) == 'png' ? 'png' : 'jpeg';

	header('Content-Type: image/' . $format, true);

	$blob = $db->openBlob('tiles', 'tile_data', $rowid);
	$out = fopen('php://output', 'w');
	stream_copy_to_stream($blob, $out);
	fclose($blob);
	fclose($out);

	exit;
}

$host = $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'];

$bbox = $db->querySingle('SELECT value FROM metadata WHERE name = \'bounds\';');
$bbox = explode(',', $bbox);
$bbox = array_map('floatval', $bbox);
$bbox = json_encode([[$bbox[1], $bbox[0]], [$bbox[3], $bbox[2]]]);

$maxZoom = $db->querySingle('SELECT max(zoom_level) FROM tiles;');
$minZoom = $db->querySingle('SELECT min(zoom_level) FROM tiles;');

?>
<!DOCTYPE html>
<html>
<head>
	<link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
	<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
	<title><?=htmlspecialchars($filename)?></title>
	<style type="text/css">
	html, body, #map {
		margin: 0;
		padding: 0;
		width: 100%;
		height: 100%;
	}
	</style>
</head>

<body>
<div id="map"></div>
<script type="text/javascript">
var map = L.map('map', {
	maxBounds: <?=$bbox?>,
    maxZoom: <?=$maxZoom?>,
    minZoom: <?=$minZoom?>
});
L.tileLayer('//<?=$host?>/{z}/{x}/{y}').addTo(map);
map.fitBounds(<?=$bbox?>);
</script>
</body>
</html>