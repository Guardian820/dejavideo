<?php

function get_extension($path) {
	$info = pathinfo($path);
	return !empty($info['extension']) ? $info['extension'] : '';
}
function get_dirname($path) {
	$info = pathinfo($path);
	return $info['dirname'];
}
function get_filename($path) {
	$info = pathinfo($path);
	return $info['basename'];
}

function format_bytes($a_bytes) {
	if ($a_bytes < 1024) {
		return $a_bytes .'&nbsp;B';
	} elseif ($a_bytes < 1048576) {
		return round($a_bytes / 1024, 0) .'&nbsp;KiB';
	} elseif ($a_bytes < 1073741824) {
		return round($a_bytes / 1048576, 0) . '&nbsp;MiB';
	}

	return round($a_bytes / 1073741824, 2) . '&nbsp;GiB';
}

function format_date($unix_timestamp) {
	return date('M j, Y h:i A', $unix_timestamp);
}

function time_ago($tm, $rcs = 1, $c_level = 1) {
	// credit: http://css-tricks.com/snippets/php/time-ago-function/
	$cur_tm = time();
	$dif = $cur_tm-$tm;
	$pds_lngh = array(
		'second' => 1,
		'minute' => 60,
		'hour'   => 3600,
		'day'    => 86400,
		'month'  => 2630880,
		'year'   => 31570560
	);
	$pds = array_keys($pds_lngh);
	$lngh = array_values($pds_lngh);
	for ($v = sizeof($lngh) - 1; $v >= 0 && ($no = $dif / $lngh[$v]) <= 1; $v--);
	if ($v < 0) {
		$v = 0;
	}
	$_tm = $cur_tm - ($dif % $lngh[$v]);
	$no = floor($no);
	if ($no <> 1) {
		$pds[$v] .= 's';
	}
	$x = sprintf("%d %s ", $no, $pds[$v]);
	if (
		(
			($rcs-1 >= 1) && ($c_level <= $rcs-1)
			|| $rcs == 0
		)
		&& ($v >= 1)
		&& ($cur_tm-$_tm > 0)
	) {
		$x .= time_ago($_tm, $rcs, $c_level + 1);
	}
	if ($no < SECONDS_OLD_BEFORE_SHOWING && strpos($pds[$v], 'second') !== false && $c_level == 1) {
		return "Downloading…";
	}
	if ($rcs <= $c_level || $v == 0) {
		return $x . 'ago';
	}
	return $x;
}

function get_codec($mime_type) {
	$codecs = $GLOBALS["ARRAY_MIME_TYPES_CODECS"];
	return $codecs[$mime_type];
}

function get_mime_type($file) {
	$finfo     = finfo_open(FILEINFO_MIME_TYPE);
	$mime_type = @finfo_file($finfo, $file);
	finfo_close($finfo);
	if ($mime_type === 'application/octet-stream' && get_extension($file) === 'webm') {
		return 'video/webm'; // solves problems with some webm files
	} else if ($mime_type === 'application/octet-stream' && get_extension($file) === 'mkv') {
		return 'video/x-matroska'; // solves problems with some mkv files
	}
	if ($mime_type !== false) {
		return $mime_type;
	}
	return 'application/octet-stream';
}

function accepted_mime_type($mime_type) {
	return array_key_exists($mime_type, $GLOBALS["ARRAY_MIME_TYPES_CODECS"]);
}

function is_safe_dir($dir) {
	return is_safe_dir_ajax($dir) &&
	       substr($dir, 0, 1) != '/';
}

function is_safe_dir_ajax($dir) {
	return (
		is_dir($dir)
		&& substr($dir, 0, 1) != '.'
		&& !preg_match('/[\\/]\./', $dir)
		&& !preg_match('/[\\/]\.{2}/', $dir)
	);
}

function get_subtitles($video) {
	$info = pathinfo($video);
	$subtitles = $info['dirname'] . DIRECTORY_SEPARATOR . 'subs' .  DIRECTORY_SEPARATOR . $info['filename'] . '.vtt';
	if (is_file($subtitles)) return $subtitles;
	$subtitles = $info['dirname'] . DIRECTORY_SEPARATOR . 'subs' .  DIRECTORY_SEPARATOR . $info['filename'] . '.srt';
	if (is_file($subtitles)) return $subtitles;
	$subtitles = $info['dirname'] . DIRECTORY_SEPARATOR . $info['filename'] . '.vtt';
	if (is_file($subtitles)) return $subtitles;
	$subtitles = $info['dirname'] . DIRECTORY_SEPARATOR . $info['filename'] . '.srt';
	if (is_file($subtitles)) return $subtitles;
	return false;
}

function get_poster($video) {
	return false;

	// does not look good at the moment
	$info = pathinfo($video);
	$poster = $info['dirname'] . DIRECTORY_SEPARATOR . 'posters' .  DIRECTORY_SEPARATOR . $info['filename'] . '.jpg';
	if (is_file($poster)) return $poster;
	$poster = $info['dirname'] . DIRECTORY_SEPARATOR . $info['filename'] . '.jpg';
	if (is_file($poster)) return $poster;
	$poster = POSTERS . DIRECTORY_SEPARATOR . $info['filename'] . '.jpg';
	if (is_file($poster)) return $poster;
	if (is_file(DEFAULT_POSTER)) return DEFAULT_POSTER;
	return false;
}

function add_recent_file($pathname, $file_md, $max_date, $recent_files, $amount) {
	$num_files = count($recent_files);
	if ($file_md > $max_date || $num_files < $amount) {
		$recent_files[$pathname] = $file_md;
		arsort($recent_files);
		if ($num_files >= $amount) {
			array_pop($recent_files);
		}
		return $recent_files;
	}
	return false;
}

function get_recent_files($path = DATA, $amount = MAX_AMOUNT_RECENT_FILES, $sync = true) {
	$dir = new DirectoryIterator($path);
	$recent_files = array();
	$max_date = 0;
	$GLOBALS['found'] = 0;
	foreach ($dir as $file) {
		$pathname = $file->getPathname();
		if (
			is_file($pathname)
			&& (accepted_mime_type(get_mime_type($pathname)) || !ONLY_ACCEPTED_FILES)
			&& substr($file->getFilename(), 0, 1) != "."
			&& !preg_match('/\.part$/i', $file->getFilename())
			&& time() - filemtime($pathname) > SECONDS_OLD_BEFORE_SHOWING
		) {
			$file_md = filemtime($pathname);
			if ($aux = add_recent_file($pathname, $file_md, $max_date, $recent_files, $amount)) {
				$recent_files = $aux;
				$max_date = end($recent_files);
			}
		}
		$is_dir_fn = $sync ? 'is_safe_dir' : 'is_safe_dir_ajax';
		if (
			$is_dir_fn($pathname) && !$file->isDot()
			&& (contains_supported_mime_types($pathname) || !ONLY_FOLDERS_WITH_ACCEPTED_FILES)
		) {
			$rec_recent_files = get_recent_files($pathname, $amount, $sync);
			foreach ($rec_recent_files as $pathname => $file_md) {
				if ($aux = add_recent_file($pathname, $file_md, $max_date, $recent_files, $amount)) {
					$recent_files = $aux;
					$max_date = end($recent_files);
				}
			}
		}
	}
	return $recent_files;
}

function print_recent_files($recent_files, $dir, $ajax = false) {
	$print = '';
	foreach ($recent_files as $path_to_file => $file_md) {
		$original_path_to_file = $path_to_file;
		$mime_type = get_mime_type($original_path_to_file);
		if ($ajax) $path_to_file = substr($path_to_file, strpos($path_to_file, DATA));
		$filename = get_display_name(get_filename($path_to_file));
		$print .= "<li class='item_recent replaceable file_recent' data-mime-type='" . $mime_type . "'><a class='title-link' href='?v=";
		$print .= rawurlencode($path_to_file);
		$print .= "&amp;d=";
		$print .= rawurlencode($dir);
		$print .= "' title='";
		$print .= $filename;
		$print .= "&#10;⌚ ";
		$print .= time_ago(filemtime($original_path_to_file));
		$print .= "'>";
		$print .= max_str_length($filename);
		$print .= "</a>";
	}
	return $print;
}

function max_str_length(
	$string,
	$length = MAX_TEXT_SIZE_RECENT_FILES,
	$ellipsis = DEFAULT_ELLIPSIS
) {
	$result = $string;
	if (strlen($string) > $length) {
		$result = trim(substr($string, 0, $length)) . $ellipsis;
	}
	return $result;
}

function tokenize_current_location($dir) {
	$tokens = array();
	$tokens[] = array(HOME_NAME, DATA);

	$dir_without_home = substr($dir, strlen(DATA) + 1);

	$rest_vals = array_filter(explode(DS, $dir_without_home));
	$rest_keys = array_map('get_display_name', $rest_vals);

	for ($i = 0, $i_max = count($rest_vals); $i < $i_max; $i++) {
		$tokens[] = array($rest_keys[$i], $rest_vals[$i]);
	}

	return $tokens;
}

function display_current_location($dir) {
	$path_trail = '';
	$current_location = '';
	$tokens = tokenize_current_location($dir);
	$num_tokens = count($tokens);
	$i = 1;
	foreach ($tokens as $token) {
		$path_trail .= $token[1];
		$dir_active = ($i !== $num_tokens) ? '' : ' dir_active';
		$link = '?d=' . rawurlencode($path_trail);
		$current_location .= "<a class='dir$dir_active' href='" . $link . "'>" . $token[0];
		if (DISPLAY_FILE_COUNT) {
			$current_location .= " <span class='file_count'>(" . count_files($path_trail, REAL_FILE_COUNT) . ")</span>";
		}
		$current_location .= "</a>";
		if ($i !== $num_tokens) {
			$current_location .= ' <span class="sep">' . SDS . '</span> ';
			$path_trail .= DS;
		}
		$i++;
	}
	return $current_location;
}

function get_page_title($video, $dir) {
	if ($video) {
		echo get_display_name(get_filename($video));
	} else {
		if ($dir !== DATA) {
			$tokens = tokenize_current_location($dir);
			echo '"';
			$title = '';
			foreach ($tokens as $token) {
				$title .= $token[0] . SDS;
			}
			echo substr($title, 0, -1);
			echo '"';
		} else {
			echo HOME_NAME;
		}
	}
}

function get_display_name($filename) {
	if (!DISPLAY_NAMES) return $filename;
	foreach ($GLOBALS["ARRAY_DISPLAY_NAMES"] as $pattern => $replacement) {
		$display_name = preg_replace($pattern, $replacement, $filename);
		if (strcmp($filename, $display_name) !== 0) {
			$stripped_separators = preg_replace('/[\._]/', ' ', $display_name);
			$stripped_double_spaces = preg_replace('/[\ ]{2,}/', ' ', $stripped_separators);
			return ucwords(strtolower(trim($stripped_double_spaces)));
		}
	}
	return ucwords(strtolower(trim($filename)));
}

function display_details($new_dir) {
	$filemtime = @filemtime($new_dir);
	$filesize = @filesize($new_dir);
	echo "<br />";
	if ($filesize !== false || $filemtime !== false) {
		echo "<span class='file_details'>";
	}
	if ($filesize !== false) {
		echo format_bytes($filesize);
	}
	if ($filesize !== false && $filemtime !== false) {
		echo " &nbsp;–&nbsp; ";
	}
	if ($filemtime !== false) {
		echo "<abbr class='timeago' title='" . date('c', $filemtime) . "'>" . format_date($filemtime, AGO_NUMBER_OF_UNITS) . "</abbr>";
	}
	if ($filesize !== false || $filemtime !== false) {
		echo "</span>";
	}
}

function count_files($path, $count_files = false) {
	$dir = new DirectoryIterator($path);
	$n = 0;
	foreach ($dir as $file) {
		if (
			is_file($path . DS . $file)
			&& (accepted_mime_type(get_mime_type($path . DS . $file))
			|| !ONLY_ACCEPTED_FILES) && substr($file, 0, 1) != "."
		) {
			$n++;
		}
		if (is_safe_dir($path . DS . $file) && !$file->isDot()) {
			$GLOBALS['found'] = 0;
			if (contains_supported_mime_types($path . DS . $file) || !ONLY_FOLDERS_WITH_ACCEPTED_FILES) {
				if (!$count_files) $n++;
				else $n += count_files($path . DS . $file, true);
			}
		}
	}
	return $n;
}

function scandir_grouped($dir, $sorting_order = SCANDIR_SORT_ASCENDING) {
	$files = scandir($dir); // future: scandir($dir, $sorting_order);
	if (SORTING === 'm') return $files;

	$no_dirs = $dirs = array();
    foreach ($files as $filename) {
		if (!is_dir($dir . DS . $filename)) $no_dirs[] = $filename;
		else $dirs[] = $filename;
    }
    return (SORTING === 'df') ? array_merge($dirs, $no_dirs) : array_merge($no_dirs, $dirs);
}

function contains_supported_mime_types($dir) {
	if ($GLOBALS['found']) return true;
	$files = scandir_grouped($dir, SCANDIR_SORT_NONE);
	foreach ($files as $filename) {
		if ($filename != "." && $filename != ".." && substr($filename, 0, 1) != ".") {
			$path_to_file = $dir . DS . $filename;
			if (!is_dir($path_to_file)) {
				if (
					(accepted_mime_type(get_mime_type($path_to_file)) || !ONLY_ACCEPTED_FILES)
					&& !preg_match('/\.part$/i', $filename)
					&& time() - @filemtime($path_to_file) > SECONDS_OLD_BEFORE_SHOWING
				) {
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

function delete_file ($path) {
	$result = array();

	try {
		$unlink = @unlink(ROOT . DS . $path);
		if ($unlink) {
			$result['success'] = true;
			return $result;
		} else {
			$result['success'] = false;
			$result['problem'] = "Permission denied.";
			return $result;
		}
	} catch (Exception $e) {
		$result['success'] = false;
		$result['problem'] = $e->getMessage();
		return $result;
	}
}

function list_files($files, $dir, $video, $list_directory, $level, $is_home = false) {
	if (DEPTH > -1 && $level > DEPTH) {
		return 0;
	}
	if ($is_home && DEPTH_FIRST_LEVEL > -1 && $level > DEPTH_FIRST_LEVEL) {
		return 0;
	}
	if ($level === 1) {
		echo '<div id="top_recent_wrapper" class="tr_placeholder standard_box" style="display: none;">';
		echo '<ol id="top_recent" class="top_recent" id="tr_placeholder"><li class="item_recent" id="recent_tag"><a href="#">Recent</a>:</li><li class="item_recent" id="loading_tag">Loading…</li></ol>';
		echo '</div>';
		echo '<script>';
		echo 'document.getElementById("listing").style.opacity = ".4";';
		echo 'document.getElementById("top_recent_wrapper").style.display = "block";';
		echo '</script>';
	}
	echo "<ul class='level-$level'>";
	foreach ($files as $filename) {
		if ($filename != "." && $filename != ".." && substr($filename, 0, 1) != ".") {
			$new_dir = $dir . DS . $filename;
			$mime_type = get_mime_type($new_dir);
			if (!is_dir($new_dir)) {
				$is_current = ($new_dir === $video) ? ' current' : '';
				if (
					accepted_mime_type($mime_type)
					&& !preg_match('/\.part$/i', $filename)
					&& time() - @filemtime($new_dir) > SECONDS_OLD_BEFORE_SHOWING
				) {
					echo "<li class='deletable'>";
					echo "<p class='file' data-mime-type='" . $mime_type . "'>";
					echo "<a href='$new_dir'>" . DOWNLOAD_ICON . "</a> ";
					echo "<a class='$is_current title-link' href='?v=" . rawurlencode($new_dir) . "&amp;d=" . rawurlencode($GLOBALS['dir']) . "' title='" . $filename . "'>";
					echo get_display_name($filename);
					echo "</a>";

					if (DISPLAY_FILE_DETAILS) {
						display_details($new_dir);
					}

					echo "</p>";

					if (CAN_DELETE_FILES && $level === 2) {
						echo '<div class="delete-icon invisible" title="Delete" data-file="' . $new_dir . '">';
						echo '<a href="#">×<span class="delete-text">Delete</span></a>';
						echo '</div>';
					}

					echo "</li>";
				} else {
					if (!ONLY_ACCEPTED_FILES) {
						echo "<li>";
						echo "<p class='file' data-mime-type='" . $mime_type . "'>";
						echo "<a href='$new_dir'>" . DOWNLOAD_ICON . "</a> ";
						echo "<a class='$is_current title-link' href='?v=" . rawurlencode($new_dir) . "&amp;d=" . rawurlencode($GLOBALS['dir']) . "' title='" . $filename . "'>";
						echo get_display_name($filename);
						echo " (" . get_mime_type($new_dir) . ")";
						echo "</a>";

						if (DISPLAY_FILE_DETAILS) {
							display_details($new_dir);
						}

						echo "</p>";
						echo "</li>";
					}
				}
			} else {
				$GLOBALS['found'] = 0;
				if (!ONLY_FOLDERS_WITH_ACCEPTED_FILES || contains_supported_mime_types($new_dir)) {
					echo "<li>";
					echo "<p>";
					echo "<a class='dir' href='?d=" . rawurlencode($new_dir) . "'>";
					echo get_display_name($filename);
					if (DISPLAY_FILE_COUNT) {
						echo " <span class='file_count'>(" . count_files($new_dir, REAL_FILE_COUNT) . ")</span>";
					}
					echo "</a>";
					echo "</p>";
					$list_directory($new_dir, $video, $level + 1, $is_home);
					echo "</li>";
				}
			}
		}
	}
	echo "</ul>";
}

function list_directory($dir, $video, $level, $is_home = false) {
	if ($files = scandir_grouped($dir, SCANDIR_SORT_ASCENDING)) {
		list_files($files, $dir, $video, __FUNCTION__, $level, $is_home);
	} else return false;
}
