<?php
	/**************************************************************************\
	* eGroupWare SiteMgr - Web Content Management                              *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id: class.Categories_BO.inc.php 33188 2010-11-28 20:27:05Z ralfbecker $ */

	class Categories_BO
	{
		/**
		 * Our storage object
		 *
		 * @var Categories_SO
		 */
		var $so;
		var $currentcats;
		var $readablecats;

		function Categories_BO()
		{
			//all sitemgr BOs should be instantiated via a globalized Common_BO object,
			$this->so = CreateObject('sitemgr.Categories_SO');
		}

		//since we need this information several times we store it once,
		//this function is called by Sites_BO after the current site is defined
		function setcurrentcats()
		{
			$this->currentcats  = $this->getpermittedcats(CURRENT_SITE_ID,'active',True);
			$this->readablecats = $this->getpermittedcatsRead();
		}

		function getCategoryOptionList()
		{
			$retval[] = array('value'=>0,'display'=>'[No Parent]');
			$list = $this->getpermittedcatsWrite();
			foreach($list as $cat_id)
			{
				$cat = $this->getCategory($cat_id);
				$padding = str_pad('',12*($cat->depth-1),'&nbsp;');
				$retval[] = array('value'=>$cat->id, 'display'=>$padding.$cat->name);
			}
			return $retval;
		}

		function getpermittedcatsRead($cat_id=False,$recurse=true)
		{
			if (!$cat_id)
			{
				$cat_id = CURRENT_SITE_ID;
			}
			if ($cat_id != CURRENT_SITE_ID)
			{
				$this->check($cat_id);
			}
			return $this->getpermittedcats($cat_id,'read',$recurse);
		}

		function getpermittedcatsWrite($cat_id=False,$recurse=true)
		{
			if (!$cat_id)
			{
				$cat_id = CURRENT_SITE_ID;
			}
			if ($cat_id != CURRENT_SITE_ID)
			{
				$this->check($cat_id);
			}
			return $this->getpermittedcats($cat_id,'write',$recurse);
		}

		function getpermittedcatsCommitable()
		{
			return $this->getpermittedcats(CURRENT_SITE_ID,'commitable',true);
		}

		function getpermittedcatsArchived()
		{
			return $this->getpermittedcats(CURRENT_SITE_ID,'archived',true);
		}

		function getpermittedcats($cat_id,$check,$recurse)
		{
			$root_list = $this->so->getChildrenIDList($cat_id);
			//error_log(__METHOD__."($cat_id,$check,$recurse) root_list=".array2string($root_list));

			$permitted_list=array();

			if ($root_list) foreach($root_list as $root_cat)
			{
				switch ($check)
				{
					case 'commitable':
						$permitted = (
							$this->so->isactive($root_cat,$GLOBALS['Common_BO']->getstates('Commit')) &&
							$GLOBALS['Common_BO']->acl->is_admin()
						);
						break;
					case 'archived':
						$permitted = (
							$this->so->isactive($root_cat,$GLOBALS['Common_BO']->getstates('Archive')) &&
							$GLOBALS['Common_BO']->acl->is_admin()
						);
						break;
					case 'active':
						$permitted = $this->so->isactive($root_cat);
						break;
					case 'read':
						$permitted = (in_array($root_cat,$this->currentcats) && $GLOBALS['Common_BO']->acl->can_read_category($root_cat));
						break;
					case 'write':
						$permitted = (in_array($root_cat,$this->currentcats) && $GLOBALS['Common_BO']->acl->can_write_category($root_cat));
				}

				if ($permitted)
				{
					$permitted_list[]=$root_cat;
				}
				//subcategories can be readable/writeable even when parent is not, but when parent is inactive subcats are too.
				elseif ($check == 'active')
				{
					continue;
				}
				if ($recurse)
				{
					$sub_list = $this->getpermittedcats($root_cat,$check,true);
					if (count($sub_list)>0)
					{
						//array_push($permitted_list, $sub_list);
						$permitted_list=array_merge($permitted_list, $sub_list);
					}
				}
			}
			//error_log(__METHOD__."($cat_id,$check,$recurse)=".array2string($permitted_list));
			return $permitted_list;
		}

		function addCategory($name, $description, $parent=False)
		{
			if (!$parent)
			{
				$parent = CURRENT_SITE_ID;
			}

			if ($GLOBALS['Common_BO']->acl->is_admin())
			{
				$cat_id = $this->so->addCategory($name, $description, $parent);
				$this->currentcats[] = $cat_id;
				$this->NotifyUsers($lang, $cat_id,$state,$cat_name);
				return $cat_id;
			}
			else
			{
				return false;
			}
		}

		//$force for use by Sites_BO, since when we are editing the files list, the concept of admin of a current site does not apply
		//$frecurse also removes subcats
		function removeCategory($cat_id,$force=False,$recurse=False)
		{
			if (!$force)
			{
				$this->check($cat_id);

				if (!$GLOBALS['Common_BO']->acl->is_admin())
				{
					return False;
				}
			}
			if ($recurse)
			{
				$children = $this->so->getChildrenIDList($cat_id);
				while (list($null,$subcat) = @each($children))
				{
					$this->removeCategory($subcat,True,True);
				}
			}
			/********************************************\
			* We have to remove the category, all the    *
			* associated pages, and all the associated   *
			* acl stuff too.  not to forget blocks       *
			\********************************************/
			$GLOBALS['Common_BO']->content->removeBlocksInPageOrCat($cat_id,0,True);
			$GLOBALS['Common_BO']->pages->removePagesInCat($cat_id,True);
			$this->so->removeCategory($cat_id);
			$GLOBALS['Common_BO']->acl->remove_location($cat_id);

			return True;
		}

		function saveCategoryInfo($cat_id, $cat_name, $cat_description, $lang, $sort_order, $state, $parent=False, $old_parent=False,$index_page_id=0)
		{
			if (!$parent)
			{
				$parent = CURRENT_SITE_ID;
			}
			$cat_info =& CreateObject('sitemgr.Category_SO', True);
			$cat_info->id = $cat_id;
			$cat_info->name = $cat_name;
			$cat_info->description = $cat_description;
			$cat_info->sort_order = $sort_order;
			$cat_info->state = $state;
			$cat_info->parent = $parent;
			$cat_info->old_parent = $old_parent ? $old_parent : $parent;
			$cat_info->index_page_id = $index_page_id;
			if ($GLOBALS['Common_BO']->acl->can_write_category($cat_id))
			{
				$this->NotifyUsers($lang, $cat_id,$state,$cat_name);
				if ($this->so->saveCategory($cat_info))
				{
					if ($this->so->saveCategoryLang($cat_id, $cat_name, $cat_description, $lang))
					{
						//reflect changes
						$this->setcurrentcats();
						return true;
					}
				}
			}
			return false;
		}

		/**
		 * Save named page as category index
		 *
		 * @param int $cat_id
		 * @param int|string $page
		 */
		function saveCategoryIndex($cat_id,$page)
		{
			if (!is_numeric($page_id=$page))
			{
				$page_id = $GLOBALS['Common_BO']->pages->so->PageToID($page);
			}
			$this->so->saveCategoryIndex($cat_id,$page_id);
		}

		function saveCategoryLang($cat_id, $cat_name, $cat_description, $lang)
		{
			$this->NotifyUsers($lang, $cat_id, -1, $cat_name);
			if ($this->so->saveCategoryLang($cat_id, $cat_name, $cat_description, $lang))
			{
				return true;
			}
			return false;
		}

		//$force is for bypassing ACL when we called from Sites_UI for building up the info for the currentsite
		//and for getting at archived categories that are not listed in current nor readablecats
		function getCategory($cat_id,$lang=False,$force=False)
		{
			if ($force || ($this->check($cat_id) && in_array($cat_id,$this->readablecats)))
			{
				return $this->so->getCategory($cat_id,$lang);
			}
			else
			{
				return false;
			}
		}

		function getCategoryancestorids($cat_id,$permittedonly=False)
		{
			if (!$cat_id)
			{
				$cat_id = CURRENT_SITE_ID;
			}
			if ($cat_id != CURRENT_SITE_ID)
			{
				$this->check($cat_id);
			}
			$result = array();
			while ($cat_id != CURRENT_SITE_ID)
			{
				if (!$permittedonly || in_array($cat_id,$this->readablecats))
				{
					$result[] = $cat_id;
				}
				$cat_info = $this->so->getCategory($cat_id);
				$cat_id = $cat_info->parent;
			}
			return $result;
		}

		function getlangarrayforcategory($cat_id)
		{
			return $this->so->getlangarrayforcategory($cat_id);
		}

		function saveCategoryPerms($cat_id, $group_access, $user_access)
		{
			if ($GLOBALS['Common_BO']->acl->is_admin())
			{
				$group_access=array_merge_recursive($GLOBALS['Common_BO']->acl->get_simple_group_list(),$group_access);
				$user_access=array_merge_recursive($GLOBALS['Common_BO']->acl->get_simple_user_list(),$user_access);
				$this->saveCatPermsGeneric($cat_id, $group_access);
				$this->saveCatPermsGeneric($cat_id, $user_access);
				return true;
			}
			else
			{
				return false;
			}
		}

		function saveCatPermsGeneric($cat_id, $user_access)
		{
			if (is_array($user_access))
			{
				reset($user_access);
				while (list($acctid, $perm_array) = each($user_access))
				{
					if (substr($acctid,0,1))
					{
						$acctid = (int) substr($acctid,1);
					}
					if (is_array($perm_array))
					{
						reset($perm_array);
						$can_read = 0;
						$can_write = 0;
						while(list($permtype, $permvalue) = each($perm_array))
						{
							switch($permtype)
							{
								case 'read':
									$can_read = true;
									break;
								case 'write':
									//write access implies read access, otherwise editing blocks would not work
									$can_read = true;
									$can_write = true;
									break;
								default:
									echo 'hmmmmmm: ' . $permtype . '<br>';
							}
						}
					}
					$GLOBALS['Common_BO']->acl->grant_permissions($acctid, $cat_id, $can_read, $can_write);
				}
			}
			else
			{
				echo 'wth!';
			}
		}

		function saveCategoryPermsfromparent($cat_id)
		{
			$cat=$this->getCategory($cat_id,false,true);
			$parent=$cat->parent;
			if ($parent)
			{
				$GLOBALS['Common_BO']->acl->copy_permissions($parent,$cat_id);
			}
		}

		function applyCategoryPermstosubs($cat_id)
		{
			$sublist = $this->getpermittedcatsWrite($cat_id);

			while (list(,$sub) = @each($sublist))
			{
				$GLOBALS['Common_BO']->acl->copy_permissions($cat_id,$sub);
			}
		}

		function removealllang($lang)
		{
			$this->so->removealllang($lang);
		}

		function migratealllang($oldlang,$newlang)
		{
			$this->so->migratealllang($oldlang,$newlang);
		}

		//make sure cat_id belongs to current site
		function check($cat_id)
		{
			//error_log(__METHOD__."($cat_id) ".function_backtrace());
			if ($cat_id == CURRENT_SITE_ID || in_array($cat_id,$this->currentcats))
			{
				return True;
			}
			elseif($GLOBALS['Common_BO']->pages->so->PageToID('404NotFound'))
			{
				$GLOBALS['egw']->redirect(sitemgr_link(array('page_name' => '404NotFound')));
			}
			else
			{
				header('HTTP/1.0 404 Not found');
				echo '<h1>'.lang('Attempt to access information outside current website').': cat_id='.htmlspecialchars($cat_id).'</h1>';
				//echo "Backtrace:<pre>".print_r(debug_backtrace(),True)."</pre>\n";
				$GLOBALS['egw']->common->egw_exit(True);
			}
		}

		function commit($cat_id)
		{
			if ($GLOBALS['Common_BO']->acl->is_admin())
			{
				$this->so->commit($cat_id);
			}
		}

		function reactivate($cat_id)
		{
			if ($GLOBALS['Common_BO']->acl->is_admin())
			{
				$this->so->reactivate($cat_id);
			}
		}

		function NotifyUsers($lang, $cat_id,$state,$cat_name)
		{
			$bo=CreateObject("sitemgr.bonotifications");
			$bo->notify_users($GLOBALS['Common_BO']->sites->current_site['site_id'],
				$cat_id,
				$state,
				$lang,
				$GLOBALS['Common_BO']->sites->current_site['sitelanguages']['0'],
				array('category_id'=>$cat_id,'lang'=>$lang),
				array(
					array('text'=>"Category name:",'translate'=>True),
					" ",$cat_name
				)
			);
		}
	}
