<?php
    /**************************************************************************\
    * eGroupWare - Knowledge Base                                              *
    * http://www.egroupware.org                                                *
    * -----------------------------------------------                          *
    *  This program is free software; you can redistribute it and/or modify it *
    *  under the terms of the GNU General Public License as published by the   *
    *  Free Software Foundation; either version 2 of the License, or (at your  *
    *  option) any later version.                                              *
    \**************************************************************************/

	/* $Id: hook_settings.inc.php 23706 2007-04-27 09:01:41Z ralfbecker $ */

	$show_tree = array(
		'all' => 'The present category and all subcategories under it',
		'only_cat' => 'The present category only'
	);

	$num_lines = array(
		'3'  => '3',
		'5'  => '5',
		'10' => '10',
		'15' => '15'
	);

	$num_comments = array(
		'5'   => '5',
		'10'  => '10',
		'15'  => '15',
		'20'  => '20',
		'All' => 'All'
	);

	$GLOBALS['settings'] = array(
		'show_tree' => array(
			'type'    => 'select',
			'label'   => 'Show articles belonging to:',
			'name'    => 'show_tree',
			'values'  => $show_tree,
			'help'    => 'When navigating through categories, choose whether the list of articles shown corresponds only to the present category, or the present category and all categories under it.',
			'default' => 'all'
		),
		'num_lines' => array(
			'type'    => 'select',
			'label'   => 'Maximum number of most popular articles, latest articles and unanswered questions to show in the main view:',
			'name'    => 'num_lines',
			'values'  => $num_lines,
			'default' => '',
			'size'    => '3'
		),
		'num_comments' => array(
			'type'    => 'select',
			'label'   => 'Maximum number of comments to show:',
			'name'    => 'num_comments',
			'values'  => $num_comments,
			'default' => '',
			'size'    => '5'
		),
		'rtfEditorFeatures' => array(
			'type'   => 'select',
			'label'  => 'Features of the editor?',
			'name'   => 'rtfEditorFeatures',
			'values' => array(
				'simple'   => lang('Simple'),
				'extended' => lang('Regular'),
				'advanced' => lang('Everything'), 
			),
			'help'   => 'You can customize how many icons and toolbars the editor shows.',
			'xmlrpc' => True,
			'admin'  => False
		),
	);
