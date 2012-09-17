<?php
/**
* @copyright	Copyright (C) 2009 - 2009 Ready Bytes Software Labs Pvt. Ltd. All rights reserved.
* @license		GNU/GPL, see LICENSE.php
* @package		PayPlans
* @subpackage	XiFramework
* @contact 		shyam@readybytes.in
*/
if(defined('_JEXEC')===false) die();

class XiChartLineChart  extends XiChart
{
	protected $_name =  'LineChart';
	
	// Store options for rendering of chart
	public $options = array(
		'height' => 700,
		'width'	 => 300,
		'wmode'  => "transparent",
		'colors' => array('#6CA6CD'));
}
