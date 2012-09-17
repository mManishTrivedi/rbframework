<?php
/**
* @copyright	Copyright (C) 2009 - 2009 Ready Bytes Software Labs Pvt. Ltd. All rights reserved.
* @license		GNU/GPL, see LICENSE.php
* @package		PayPlans
* @subpackage	Frontend
* @contact 		shyam@readybytes.in
*/
if(defined('_JEXEC')===false) die();

class XiAbstractJ15HelperToolbar extends XiAbstractHelperToolbarBase
{
	public function save()
	{
		parent::_save();
	}
	
	public function apply()
	{
		parent::_apply();
	}
	
	public function savenew()
	{
		parent::_savenew();
	}
	
	public function cancel($task = 'cancel', $alt = 'Close')
	{
		parent::_cancel();
	}
	
    public function delete($list='true', $alt='')
	{
		parent::_delete($list);
	}
	
	public function deleteRecord($list='false', $alt='')
	{
		parent::_deleteRecord($list);
	}
	
	public function searchpayplans()
	{
		parent::searchpayplans();
	}
}

class XiAbstractHelperToolbar extends XiAbstractJ15HelperToolbar
{}