<?php
// $Id: diff.php 19415 2005-10-14 14:08:53Z ralfbecker $

require('parse/main.php');
require('parse/macros.php');
require('parse/html.php');
require(TemplateDir . '/diff.php');
require('lib/diff.php');

// Compute difference between two versions of a page.
function action_diff()
{
	global $pagestore, $page, $ver1, $ver2, $ParseEngine;

	$p1 = $pagestore->page($page);
	$p1->version = $ver1;
	$p2 = $pagestore->page($page);
	$p2->version = $ver2;

	$diff = diff_compute($p1->read(), $p2->read());

	template_diff(array('page'      => $p2->as_array(),
											'diff_html' => diff_parse($diff),
											'html'      => parseText($p2->text, $ParseEngine, $page),
											'editable'  => $p2->acl_check(),
											'timestamp' => $p2->time));
}
?>
