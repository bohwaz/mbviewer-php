<?php

// Converts a directory structure to a MBTiles file

if (empty($argv[1]) || empty($argv[2])) {
	die('Usage: script.php directory target.mbtiles' . PHP_EOL);
}

$db = new SQLite3($argv[2]);

$db->exec('CREATE TABLE IF NOT EXISTS tiles (
            zoom_level integer,
            tile_column integer,
            tile_row integer,
            tile_data blob);
CREATE TABLE IF NOT EXISTS metadata
        (name text, value text);
CREATE UNIQUE INDEX IF NOT EXISTS tile_index on tiles
        (zoom_level, tile_column, tile_row);
');

$files = glob($argv[1] . '/*/*/*');

foreach ($files as $file) {
	$dir = dirname($file);
	$y = preg_replace('/\..*$/', '', basename($file));
	$x = basename($dir);
	$z = basename(dirname($dir));

	$db->exec(sprintf('INSERT INTO tiles VALUES (%d, %d, %d, zeroblob(%d));', $z, $x, $y, filesize($file)));
	$id = $db->lastInsertRowID();
	$r = $db->openBlob('tiles', 'tile_data', $id, 'main', SQLITE3_OPEN_READWRITE);
	fwrite($r, file_get_contents($file));
	fclose($r);
	echo '.';
}

