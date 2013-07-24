<?php
/**
 * eGroupWare: About informations
 *
 * rewrite of the old PHPLib based about page
 * it now uses eTemplate
 *
 * This is NO typical eTemplate application as it is not stored in the
 * correct namespace
 *
 * LICENSE:  GPL.
 *
 * @package     api
 * @subpackage  about
 * @author      Sebastian Ebling <hudeldudel@php.net>
 * @author      Ralf Becker <RalfBecker@outdoor-training.de>
 * @license     http://www.gnu.org/copyleft/gpl.html
 * @link        http://www.egroupware.org
 * @version     SVN: $Id: class.about.inc.php 37844 2012-01-31 13:45:57Z leithoff $
 */

/**
 * Shows informations about eGroupWare
 *  - general information
 *  - installed applications
 *  - installed templates
 *  - installed languages
 *
 * Usage:
 * <code>
 *  $aboutPage = new about();
 * </code>
 * The constuctor will do all
 *
 * There is no typical so, bo ui structure, this class is all in one
 *
 * @since		1.4
 */
class about
{
	/**
	 * constructor of about class
	 * decides to show tab- or detail view
	 *
	 * @since	1.4
	 */
	function about()
	{
		// list or detail view
		$name = 'eGroupWare';
		$detail = false;
		$nonavbar = false;

		// application detail?
		if (isset($_GET['app']) && $_GET['app'] != 'eGroupWare') {
			$name = basename($_GET['app']);
			$type = 'application';
			$detail = true;
		}

		// template detail?
		if (isset($_GET['template']) && $_GET['template'] != 'eGroupWare') {
			$name = basename($_GET['template']);
			$type = 'template';
			$detail = true;
		}

		// navbar or not
		if (isset($_GET['nonavbar'])) {
			$nonavbar = $_GET['nonavbar'];
		}


		if ($detail) {
			$this->_detailView($name, $type, $nonavbar);
		} else {
			$this->_listView();
		}
	}


	/**
	 * output of list view
	 * collects data and shows a tabbed view that lists
	 *  - general informations
	 *  - installed applications
	 *  - installed templates
	 *  - installed languages
	 *
	 * @return nothing
	 *
	 * @access  private
	 * @since	1.4
	 */
	function _listView()
	{
		$text_content = str_replace('GPLLINK',self::$knownLicenses['GPL'],'
<p><b>EGroupware is a <a href="GPLLINK" title="read more about open source and the GPL" target="_blank">free</a> 
enterprise ready groupware software</b> for your network. It enables you to manage contacts, appointments, todos 
and many more for your whole business.</p>
<p><b>EGroupware is a groupware server.</b> It comes with a native web-interface which allowes to access your data 
from any platform all over the planet. Moreover you also have the choice to access the EGroupware server with 
your favorite groupware client (Kontact, Evolution, Outlook, iCal, Lightning) and also with your mobile or PDA 
via SyncML.</p>
<p><b>EGroupware is international.</b> At the time, it supports more than 
<a href="http://www.egroupware.org/languages" target="_blank">25 languages</a> including rtl support.</p>
<p><b>EGroupware is platform independent.</b> The server runs on Linux, Mac, Windows and many more other operating systems. 
On the client side, all you need is a internet browser such as Firefox, Safari, Chrome, Konqueror or Internet Explorer 
and many more.</p>
<p><b>EGroupware is developed by <a href="http://www.stylite.de/" target="_blank">Stylite AG</a></b> with contributions
from community developers.</p>
<br />
<p><b>For more information visit the <a href="http://www.egroupware.org" target="_blank">EGroupware Website</a></b></p>');
		
		// get informations about the applications
		$apps = array();
		$apps[] = ''; // first empty row for eTemplate
		foreach (isset($GLOBALS['egw_info']['user']['apps']['admin']) ?
			$GLOBALS['egw_info']['apps'] : $GLOBALS['egw_info']['user']['apps'] as $app => $appinfo)
		{
			$info = $this->_getParsedAppInfo($app);
			$apps[] = array(
				'appImage'		=> '<img src="'.$info['image'].'" />',
				'appName'		=> $appinfo['title'],
				'appAuthor' 	=> $info['author'],
				'appMaintainer'	=> $info['maintainer'],
				'appVersion'	=> $info['version'],
				'appLicense'	=> $this->_linkLicense($info['license']),
				'appDetails'	=> '<a href="'.$GLOBALS['egw_info']['server']['webserver_url'].'/about.php?app='.$app.'&nonavbar=true" onclick="egw_openWindowCentered2(this.href,this.target,750,410,'."'yes'".'); return false;"><img src="'.$GLOBALS['egw']->common->image('phpgwapi','view.png').'" /></a>'
			);
		}

		// get informations about the templates
		$templates = array();
		$templates[] = ''; // first empty row for eTemplate
		foreach($GLOBALS['egw']->common->list_templates() as $template => $templateinfo) {
			$info = $this->_getParsedTemplateInfo($template);
			$templates[] = array(
				'templateImage'		=> '<img src="'.$info['image'].'" />',
				'templateName'		=> $templateinfo['name'],
				'templateAuthor'	=> $info['author'],
				'templateMaintainer'=> $info['maintainer'],
				'templateVersion'	=> $info['version'],
				'templateLicense'	=> $this->_linkLicense($info['license']),
				'templateDetails'	=> '<a href="'.$GLOBALS['egw_info']['server']['webserver_url'].'/about.php?template='.$template.'&nonavbar=true" onclick="egw_openWindowCentered2(this.href,this.target,750,410,'."'yes'".'); return false;"><img src="'.$GLOBALS['egw']->common->image('phpgwapi','view.png').'" /></a>'
			);
		}

		// get informations about installed languages
		$translations = array();
		$translations[] = ''; // first empty row for eTemplate
		$langs = $GLOBALS['egw']->translation->get_installed_langs();
		foreach($GLOBALS['egw']->translation->get_installed_langs() as $translation => $translationinfo) {
			$translations[] = array(
				'langName'	=>	$translationinfo.' ('.$translation.')'
			);
		}

		$changelog = EGW_SERVER_ROOT.'/doc/rpm-build/debian.changes';
		// fill content array for eTemplate
		$content = array(
			'apiVersion'	=> '<p>'.lang('eGroupWare API version').' '.$GLOBALS['egw_info']['server']['versions']['phpgwapi'].'</p>',
			'applications'	=> $apps,
			'templates'		=> $templates,
			'translations'	=> $translations,
			'text_content'  => $text_content,
			'changelog'     => file_exists($changelog) ? file_get_contents($changelog) : 'not available',
		);

		$tmpl =& CreateObject('etemplate.etemplate', 'phpgwapi.about.index');
		$tmpl->exec('phpgwapi.about.index', $content);
	}


	/**
	 * output of detail view for applications or templates
	 *
	 * @param	string	$name		application/template name
	 * @param	string	$type		can be 'application' or 'template'	:default $type='application'
	 * @param	string	$nonavbar 	don't show navbar	:default $nonavbar=false
	 * @return	nothing
	 *
	 * @access	private
	 * @since	1.4
	 */
	function _detailView($name, $type='application', $nonavbar=false)
	{
		// get the informations
		switch ($type) {
			case 'application':
				$info = $this->_getParsedAppInfo($name);
				break;
			case 'template':
				$info = $this->_getParsedTemplateInfo($name);
				break;
		}

		// app names are translated, template names not...
		if ($type == 'application') {
			$translatedName = lang($name);
		} else {
			$translatedName = $name;
		}

		// fill content array
		$content = array(
			'image'			=> '<img src="'.$info['image'].'" />',
			'name'			=> '<h2>'.$translatedName.'</h2>',
			'description'	=> '<p>'.$info['description'].'</p>',
			'note'			=> $info['note'],
			'author'		=> $info['author'],
			'maintainer'	=> $info['maintainer'],
			'version'		=> $info['version'],
			'license'		=> $this->_linkLicense($info['license'])
			);

		$tmpl =& CreateObject('etemplate.etemplate', 'phpgwapi.about.detail');
		if ($nonavbar) {
			$tmpl->exec('phpgwapi.about.detail', $content, array(), array(), array(), 2);
		} else {
			$GLOBALS['egw_info']['flags']['app_header'] = lang('About %1', $translatedName);
			$tmpl->exec('phpgwapi.about.detail', $content);
		}
	}


	/**
	 * parse template informations from setup.inc.php file
	 *
	 * @param   string  $template	template template name
	 * @return  array   html formated informations about author(s),
	 *                  maintainer(s), version, license of the
	 *                  given application
	 *
	 * @access  private
	 * @since   1.4
	 */
	function _getParsedTemplateInfo($template)
	{
		// define the return array
		$ret = array(
		'image'			=> (file_exists($GLOBALS['egw_info']['template'][$template]['icon'])) ? $GLOBALS['egw_info']['template'][$template]['icon'] : $GLOBALS['egw']->common->image('thisdoesnotexist',array('navbar','nonav')),
			'author'        => '',
			'maintainer'    => '',
			'version'       => '',
			'license'       => '',
			'description'	=> '',
			'note'			=> ''
		);

		if (!file_exists(EGW_INCLUDE_ROOT . "/phpgwapi/templates/$template/setup/setup.inc.php")) {
			return $ret;
		}

		include(EGW_INCLUDE_ROOT . "/phpgwapi/templates/$template/setup/setup.inc.php");

		$ret['author'] = $this->_getHtmlPersonalInfo($GLOBALS['egw_info']['template'][$template], 'author');
		$ret['maintainer'] = $this->_getHtmlPersonalInfo($GLOBALS['egw_info']['template'][$template], 'maintainer');
		$ret['version'] = $GLOBALS['egw_info']['template'][$template]['version'];
		$ret['license'] = $GLOBALS['egw_info']['template'][$template]['license'];
		$ret['description'] = $GLOBALS['egw_info']['template'][$template]['description'];
		$ret['note'] = $GLOBALS['egw_info']['template'][$template]['note'];

		return $ret;
	}


	/**
	 * parse application informations from setup.inc.php file
	 *
	 * @param	string	$app	application name
	 * @return	array	html formated informations about author(s),
	 *					maintainer(s), version, license of the
	 *					given application
	 *
	 * @access  private
	 * @since   1.4
	 */
	function _getParsedAppInfo($app)
	{
		// we read all setup files once, as no every app has it's own file
		static $setup_info;
		if (is_null($setup_info))
		{
			foreach($GLOBALS['egw_info']['apps'] as $_app => $_data)
			{
				if (file_exists($file = EGW_INCLUDE_ROOT.'/'.$_app.'/setup/setup.inc.php'))
				{
					include($file);
				}
			}
		}
		$app_info  = $GLOBALS['egw_info']['apps'][$app];

		// define the return array
		$ret = array(
			'image'			=> $GLOBALS['egw']->common->image(isset($app_info['icon_app'])?$app_info['icon_app']:$app,
				isset($app_info['icon'])?$app_info['icon']:array('navbar','nonav')),
			'author'		=> '',
			'maintainer'	=> '',
			'version'		=> $app_info['version'],
			'license'		=> '',
			'description'	=> '',
			'note'			=> ''
		);

		if (isset($setup_info[$app]))
		{
			$ret['author'] = $this->_getHtmlPersonalInfo($setup_info[$app], 'author');
			$ret['maintainer'] = $this->_getHtmlPersonalInfo($setup_info[$app], 'maintainer');
			if ($app_info['version'] != $setup_info[$app]['version']) $ret['version'] .= ' ('.$setup_info[$app]['version'].')';
			$ret['license'] = $setup_info[$app]['license'];
			$ret['description'] = $setup_info[$app]['description'];
			$ret['note'] = $setup_info[$app]['note'];
		}
		return $ret;
	}


	/**
	 * helper to parse author and maintainer info from setup_info array
	 *
	 * @param	array	$setup_info	setup_info[$app] array
	 *								($GLOBALS['egw_info']['template'][$template] array for template informations)
	 * @param	string	$f			'author' or 'maintainer', default='author'
	 * @return	string	html formated informations about author/maintainer
	 *
	 * @access  private
	 * @since   1.4
	 */
	function _getHtmlPersonalInfo($setup_info, $f = 'author')
	{
		$authors = array();
			// get the author(s)
			if ($setup_info[$f]) {
				// author is set
				if (!is_array($setup_info[$f])) {
						// author is no array
						$authors[0]['name'] = $setup_info[$f];
						if ($setup_info[$f.'_email']) {
							$authors[0]['email'] = $setup_info[$f.'_email'];
						}
						if ($setup_info[$f.'_url']) {
							$authors[0]['url'] = $setup_info[$f.'_url'];
						}

				} else {
						// author is array
						if ($setup_info[$f]['name']) {
							// only one author
							$authors[0]['name'] = $setup_info[$f]['name'];
							if ($setup_info[$f]['email']) {
								$authors[0]['email'] = $setup_info[$f]['email'];
							}
							if ($setup_info[$f]['url']) {
								$authors[0]['url'] = $setup_info[$f]['url'];
							}
						} else {
							// may be more authors
							foreach ($setup_info[$f] as $number => $values) {
								if ($setup_info[$f][$number]['name']) {
										$authors[$number]['name'] = $setup_info[$f][$number]['name'];
								}
								if ($setup_info[$f][$number]['email']) {
										$authors[$number]['email'] = $setup_info[$f][$number]['email'];
								}
								if ($setup_info[$f][$number]['url']) {
										$authors[$number]['url'] = $setup_info[$f][$number]['url'];
								}
							}
						}
				}
			}

		// html format authors
		$s = '';
		foreach ($authors as $author) {
			if ($s != '') {
					$s .= '<br />';
			}
			$s .= lang('name').': '.$author['name'];
			if ($author['email']) {
					$s .= '<br />'.lang('email').': <a href="mailto:'.$author['email'].'">'.$author['email'].'</a>';
			}
			if ($author['url']) {
					$s .= '<br />'.lang('url').': <a href="'.$author['url'].'" target="_blank">'.$author['url'].'</a>';
			}
		}
		return $s;
	}

	static public $knownLicenses = array(
		'GPL'	=> 'http://opensource.org/licenses/gpl-2.0.php',
		'LGPL'	=> 'http://opensource.org/licenses/lgpl-2.1.php',
		'GPL3'	=> 'http://opensource.org/licenses/gpl-3.0.php',
		'LGPL3'	=> 'http://opensource.org/licenses/lgpl-3.0.php',
		'PHP'   => 'http://opensource.org/licenses/php.php',
	);

	
	/**
	 * surround license string with link to license if it is known
	 *
	 * @param   string  $license	the license to surround with link
	 * @return  string  linked licence if known, $licence if not known
	 *
	 * @access  private
	 * @since   1.4
	 */
	function _linkLicense($license)
	{
		$name = is_array($license) ? $license['name'] : $license;
		$url = is_array($license) && isset($license['url']) ? $license['url'] : '';

		if (!$url && isset(self::$knownLicenses[strtoupper($name)]))
		{
			$url = $knownLicenses[$name=strtoupper($name)];
		}

		return !$url ? $name : '<a href="'.htmlspecialchars($url).'" target="_blank">'.htmlspecialchars($name).'</a>';
	}
}
