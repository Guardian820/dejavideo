<?php

function get_extension ($file) {
	$info = pathinfo($file);
	return $info['extension'];
}

function get_parent_folder ($path) {
	return substr($path, 0, strrpos($path, '/'));
}

function get_codec ($mime_type) {
	$codecs = $GLOBALS["ARRAY_MIME_TYPES_CODECS"];
	return $codecs[$mime_type];
}

function get_mime_type ($file) {
	$finfo     = finfo_open(FILEINFO_MIME_TYPE);
	$mime_type = finfo_file($finfo, $file);
	finfo_close($finfo);
	if ($mime_type === 'application/octet-stream' && get_extension($file) === 'webm') {
		return 'video/webm'; // solves problems with some webm files
	}
	return $mime_type;
}

function accepted_mime_type ($mime_type) {
	return array_key_exists($mime_type, $GLOBALS["ARRAY_MIME_TYPES_CODECS"]);
}

function is_safe_dir ($dir) {
	return is_dir($dir) && 
	       substr($dir, 0, 1) != '.' && 
	       substr($dir, 0, 1) != '/' && 
	       !preg_match('/[\\/]\.{2}/', $dir);
}

function get_subtitles ($video) {
	$info = pathinfo($video);
	$subtitles = $info['dirname'] . DIRECTORY_SEPARATOR . 'subs' .  DIRECTORY_SEPARATOR . $info['filename'] . '.vtt';
	if (is_file($subtitles)) {
		return $subtitles;
	} else {
		$subtitles = $info['dirname'] . DIRECTORY_SEPARATOR . $info['filename'] . '.vtt';
		if (is_file($subtitles)) {
			return $subtitles;
		}
	}
	return false;
}

function display_current_location ($dir) {
	$path_trail = '';
	$current_location = '';
	$tokens = array_filter(explode('/', $dir));
	foreach ($tokens as $token) {
		$path_trail .= $token;
		$dir_active = ($token !== end($tokens)) ? '' : ' dir_active';
		$current_location .= "<a class='dir$dir_active' href='?v=" . rawurlencode($path_trail) . "#listing'>" . get_display_name($token) . "</a>";
		if ($token !== end($tokens)) {
			$current_location .= ' ' . SCREEN_DIRECTORY_SEPARATOR . ' ';
			$path_trail .= '/';
		}
	}
	return $current_location;
}

// This function helps pirates only. Don't look at it! ;)
function get_display_name($filename) {
	if (!DISPLAY_NAMES) return $filename;
	foreach ($GLOBALS["ARRAY_DISPLAY_NAMES"] as $pattern => $replacement) {
		$display_name = preg_replace($pattern, $replacement, $filename);
		if (strcmp($filename, $display_name) !== 0)	{
			$display_name = str_replace(".", " ", $display_name);
			$display_name = str_replace("_", " ", $display_name);
			return $display_name;
		}
	}
	return $filename;
}

function scandir_grouped ($dir, $sorting_order = SCANDIR_SORT_ASCENDING) {
	$files = scandir($dir, $sorting_order);
	$no_dirs = $dirs = array();
    foreach($files as $filename) {
		if (!is_dir($dir . "/" . $filename)) $no_dirs[] = $filename;
		else $dirs[] = $filename;
    }
    return array_merge($no_dirs, $dirs);
}

function contains_supported_mime_types ($dir) {
	if ($GLOBALS['found']) return true;
	$files = scandir_grouped($dir, SCANDIR_SORT_NONE);
	foreach ($files as $filename) {
		if ($filename != "." && $filename != ".." && substr($filename, 0, 1) != ".") {
			$path_to_file = $dir . "/" . $filename;
			if (!is_dir($path_to_file)) {
				if (accepted_mime_type(get_mime_type($path_to_file))) {
					$GLOBALS['found'] = 1;
					return true;
				}
			} else {
				contains_supported_mime_types($path_to_file);
			}
		}
	}
	return $GLOBALS['found'];
}

function list_files ($files, $dir, $video, $list_directory, $level) {
	if (DEPTH > -1 && $level > DEPTH) return 0;
	echo "<ul class='level-$level'>";
	foreach ($files as $filename) {
		if ($filename != "." && $filename != ".." && substr($filename, 0, 1) != ".") {
			$new_dir = $dir . "/" . $filename;
			if (!is_dir($new_dir)) {
				if (accepted_mime_type(get_mime_type($new_dir))) {
					$is_current = ($new_dir === $video) ? ' current' : '';
					echo "<li>";
					echo "<p><a class='file$is_current' href='?v=" . rawurlencode($new_dir) . "&amp;d=" . rawurlencode($GLOBALS['dir']) . "' title='" . $filename . "'>";
					echo get_display_name($filename);
					echo "</a></p>";
					echo "</li>";
				} else {
					if (!ONLY_ACCEPTED_FILES) {
						echo "<li>";
						echo "<p class='file unsupported' title='" . $filename . "'>" . get_display_name($filename) . " [" . get_mime_type($new_dir) . " unsupported]</p>";
						echo "</li>";
					}
				}
			} else {
				$GLOBALS['found'] = 0;
				if (!ONLY_FOLDERS_WITH_ACCEPTED_FILES || contains_supported_mime_types($new_dir)) {
					echo "<li>";
					echo "<p><a class='dir' href='?v=" . rawurlencode($new_dir) . "#listing'>" . get_display_name($filename) . "</a></p>";
					$list_directory($new_dir, $video, $level + 1);
					echo "</li>";
				}
			}
		}
	}
	echo "</ul>";
}

function list_directory ($dir, $video, $level) {
	if ($files = scandir_grouped($dir, SCANDIR_SORT_ASCENDING)) {
		list_files($files, $dir, $video, __FUNCTION__, $level);
	} else return false;
}