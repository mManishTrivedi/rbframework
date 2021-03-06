<?php

/**
* @copyright	Copyright (C) 2009 - 2012 Ready Bytes Software Labs Pvt. Ltd. All rights reserved.
* @license		GNU/GPL, see LICENSE.php
* @package 		Rb_Ecommerce
* @subpackage	Front-end
* @contact		team@readybytes.in
*/

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

/** 
 * Invoice Lib
 * @author Gaurav Jain
 */
class Rb_EcommerceInvoice extends Rb_EcommerceLib
{
	protected $invoice_id 			= 0;
	protected $object_id 			= 0;
	protected $object_type 			= 0;
	protected $buyer_id 			= 0;
	protected $master_invoice_id 	= 0;
	protected $currency 			= 'USD';
	protected $sequence 			= 0;
	protected $serial 				= 0;
	protected $status 				= 0;
	protected $title 				= '';
	protected $expiration_type 		= '';
	
	/**
	 * @var Rb_Registry
	 */
	protected $time_price 			= '';
	protected $recurrence_count 	= 0;
	protected $subtotal 			= 0;
	protected $total 				= 0;
	protected $notes 				= '';
	protected $params 				= '';
	protected $created_date 		= null;
	protected $modified_date 		= null;
	protected $paid_date 			= null;
	protected $refund_date 			= null;
	protected $due_date 			= null;
	protected $issue_date 			= null;
	
	// processor fields	
	protected $processor_type 		= '';
	protected $processor_config		= '';
	protected $processor_data		= '';
	
	protected $_transactions		= array();
	protected $_modifiers			= array();
	
    const STATUS_NONE				= 0;	
	const STATUS_DUE		   		= 401;
	const STATUS_PAID 			   	= 402;
	const STATUS_REFUNDED		   	= 403;
	const STATUS_INPROCESS			= 404;
	const STATUS_EXPIRED			= 405;
	
	/**
	 * Gets the instance of Rb_EcommerceInvoice
	 * 
	 * @param  integer  $id    		Unique identifier of input entity
	 * @param  string   $type  		
	 * @param  mixed    $data  	Data to be binded with the object
	 * @param  mixed	$dummy		Dummy arg, if its not here then PHP will give warning (while development mode is on)
	 * 
	 * @return Rb_EcommerceInvoice  Instance of Rb_EcommerceInvoice
	 */	
	public static function getInstance($id = 0, $data = null, $dummy1 = null, $dummy2 = null)
	{
		return parent::getInstance('invoice', $id, $data);
	}
	
	/**
	 * Reset all the properties  of  curent object to their default values
	 * 
	 * @return  Object Rb_EcommerceInvoice Instance of Rb_EcommerceInvoice
	 */
	public function reset()
	{		
		$this->invoice_id 			= 0;
		$this->object_id 			= 0;
		$this->object_type 			= 0;
		$this->buyer_id 			= 0;
		$this->master_invoice_id 	= 0;
		$this->currency 			= 'USD';
		$this->sequence 			= 0;
		$this->serial 				= 0;
		$this->status 				= 0;
		$this->title 				= '';
		$this->expiration_type 		= '';
		$this->time_price 			= new Rb_Registry();
		$this->recurrence_count 	= 0;
		$this->subtotal 			= 0;
		$this->total 				= 0;
		$this->notes 				= '';
		$this->params 				= '';
		$this->created_date 		= new Rb_Date();
		$this->modified_date 		= new Rb_Date();
		$this->paid_date 			= null;
		$this->refund_date			= null;
		$this->due_date 			= new Rb_Date();
		$this->issue_date 			= new Rb_Date();
		$this->processor_type 		= '';
		$this->processor_config		= new Rb_Registry();
		$this->processor_data		= new Rb_Registry();
	
		return $this;
	}
	

	public function refresh()
	{
		return $this->__createDoCalculation()->_loadModifiers()->_loadTransactions();
	} 
	
	public function bind($data, $ignore = array())
	{
		if(is_array($data)){
			$data = (object) $data;		
		}
		
		if(isset($data->processor_config)){
			$this->processor_config = new Rb_Registry() ;
		}
		
		parent::bind($data, $ignore);
		
		if(!$this->getId()){
			return $this;
		}

		// load transactions and modifiers
		$this->_loadTransactions();
		//$this->_loadModifiers();

		// if invoice is still unpaid then every time need to re-calculation (with modifiers)
		if ($this->getStatus() == self::STATUS_DUE ) {
			$this->refresh();
		}
		
		return $this;
	}
	
	protected function _loadTransactions()
	{
		$transactions = Rb_EcommerceFactory::getInstance('transaction', 'model')
									->loadRecords(array('invoice_id' => $this->getId()));

		$this->_transactions = null;
		
		if(count($transactions) >0 ){
			foreach($transactions as $transaction){
				$this->_transactions[$transaction->transaction_id] = Rb_EcommerceTransaction::getInstance($transaction->transaction_id, $transaction);
			}
		}
		
		return $this;
	}
	
	protected function _loadModifiers()
	{
		$m_helper = Rb_EcommerceFactory::getHelper('modifier');
		$this->_modifiers = $m_helper->get(array('invoice_id' => $this->getId()), true);
		$total = $m_helper->getTotal($this->getSubtotal(), $this->_modifiers);
		$this->set('total', $total);
		return $this;
	}
	
	public function isMaster()
	{		
		if($this->master_invoice_id){
			return false;
		}
		
		return true;
	}
	
	public function getBuyer()
	{
		return $this->buyer_id;
	}
	
	public function getExpirationType()
	{
		return $this->expiration_type;
	}
	
	public function getStatus()
	{
		return $this->status;
	}	
	
	public function getSubtotal()
	{
		return $this->subtotal;
	}
	
	public function getProcessorType()
	{
		if(!$this->isMaster()){
			$master = $this->getMasterInvoice();
			return $master->getProcessorType();
		}
		
		return $this->processor_type;
	}
	
	public function getTransactions()
	{
		return $this->_transactions;
	}
	
	/**
	 * @return object Rb_EcommerceTransaction
	 */
	public function getTransaction($what = '', $value = '')
	{
		if(empty($what)){
			return array_pop($this->_transactions);
		}
		
		foreach($this->_transactions as $transaction){
			$transaction_arr = $transaction->toArray();
			if($transaction_arr[$what] == $value){
				return $transaction;
			}
		}
		
		//XITODO : raise error
		return false;
	}
	
	/**
	 * @return Rb_Registry
	 */
	public function getProcessorData($inArray = false)
	{	
		// if not master invoice	
		if(!$this->isMaster()){
			$master = $this->getMasterInvoice();
			return $master->getProcessorData($inArray);		
		}
		
		// in case of master invoice
		if($inArray){
			return $this->processor_data->toArray();			
		}
		
		return $this->processor_data->toObject();
	}
	
	public function setProcessorData($data)
	{
		$this->processor_data->bind($data);
		return $this;
	}

	/**
	 * @return Rb_Registry
	 */
	public function getProcessorConfig($inArray = false)
	{		
		if($inArray){
			return $this->processor_config->toArray();			
		}
		
		return $this->processor_config->toObject();
	}
	
	public function setProcessorConfig($config)
	{
		$this->processor_config->bind($config);
		
		return $this;
	}
	
	public function getProcessor()
	{
		if(!$this->isMaster()){
			$master = $this->getMasterInvoice();
			return $master->getProcessor();
		}
		
		$helper = Rb_EcommerceFactory::getHelper();
		return $helper->processor->getInstance($this->processor_type, $this->getProcessorConfig());	
	}
	
	public function getMasterInvoice()
	{
		if($this->isMaster()){
			return $this;
		}
		
		return Rb_EcommerceInvoice::getInstance($this->master_invoice_id);
	}
	
	public function getModifiers()
	{
		return $this->_modifiers;
	}
	
	public static function getStatusList()
	{
		return array(
            self::STATUS_NONE		=> JText::_('PLG_SYSTEM_RBSL_ECOMMERCE_INVOICE_STATUS_NONE'),
			self::STATUS_DUE 		=> JText::_('PLG_SYSTEM_RBSL_ECOMMERCE_INVOICE_STATUS_DUE'),
			self::STATUS_PAID		=> JText::_('PLG_SYSTEM_RBSL_ECOMMERCE_INVOICE_STATUS_PAID'),
			self::STATUS_REFUNDED	=> JText::_('PLG_SYSTEM_RBSL_ECOMMERCE_INVOICE_STATUS_REFUNDED'),
			self::STATUS_INPROCESS	=> JText::_('PLG_SYSTEM_RBSL_ECOMMERCE_INVOICE_STATUS_INPROCESS'),
			self::STATUS_EXPIRED	=> JText::_('PLG_SYSTEM_RBSL_ECOMMERCE_INVOICE_STATUS_EXPIRED')		
		);
	}
	
	public function getChildInvoices($status = array(self::STATUS_PAID), $instance_require = false)
	{		
		$filter 	= array('master_invoice_id' => $this->getId(), 'status' => array(array('IN', '('.implode(",", $status).')')));
		$records 	= $this->getModel()->loadRecords($filter);
		
		if($instance_require === true){
			$invoices = array();
			foreach($records as $record){
				$invoices[$record->invoice_id] = Rb_EcommerceInvoice::getInstance($record->invoice_id, $record);
			}
			
			return $invoices;
		}
		
		return $records;
	}
	
	public function create($data, $master = false)
	{
		Rb_Error::assert(!$this->getId());
	
		if(is_array($data)){
			$data = (object) $data;
		}
		
		$this->title 				= $data->title;
		$this->object_id 			= $data->object_id;
		$this->object_type 			= $data->object_type;
		$this->buyer_id 			= $data->buyer_id;
		$this->currency 			= $data->currency;
		$this->serial 				= isset($data->serial) ? $data->serial : ''; // XITODO :  autoincrement
		$this->status				= isset($data->status) ? $data->status : self::STATUS_NONE;
		$this->expiration_type 		= isset($data->expiration_type) ? $data->expiration_type : RB_ECOMMERCE_EXPIRATION_TYPE_FIXED;		
		$this->recurrence_count		= isset($data->recurrence_count) ? $data->recurrence_count : 1;  // XITODO : is required for child invoice ??
		$this->time_price->bind($data->time_price);		
		$this->issue_date			= isset($data->issue_date) ? $data->issue_date : new Rb_Date();
		$this->due_date				= isset($data->due_date)   ? $data->due_date   : new Rb_Date();
		$this->subtotal				= isset($data->subtotal)	?	$data->subtotal  : 0 ;
		$this->notes				= isset($data->notes)	? $data->notes : '';
		
		if($master){
			$this->master_invoice_id 	= 0;
			$this->sequence 			= 1;
		}
		else{
			$this->master_invoice_id 	= $data->master_invoice_id;
			$this->sequence 			= $data->sequence;		
		}
		
		$this->__createDoCalculation($master, $this->sequence);		

		if(isset($data->processor_type)){
			$this->__createSetProcessor($data->processor_type, $data->processor_config);
		}

		return $this->save();
	}
		
	private function __createDoCalculation($master = false, $counter = 1)
	{
		$time_price = json_decode($this->time_price);
		$time_price = (array) $time_price;
		
		if($master){
			$price 	= array_shift($time_price['price']);			
		}
		else{
			$prices = array_values($time_price['price']);
			if(isset($prices[$counter-1])){
				$price = $prices[$counter-1];								
			}
			else{
				$price = array_pop($prices);
			}
		}
		
		$this->subtotal = $price;
		$this->total 	= $price;
		
		return $this;
	}
	
	private function __createSetProcessor($processor_type, $config)
	{
		$this->processor_type = $processor_type;
				
		if(empty($config)){
			// XITODO : get default config
			$config = null;
		}
		
		$this->setProcessorConfig($config);
		return $this;
	}
	
	private function __requestGetPaymentData()
	{
		$payment_data 	= new stdClass();
		// for fixed payment
		$payment_data->invoice_number 	= $this->getHelper()->create_invoice_number($this->invoice_id);
		$payment_data->invoice_id 		= $this->invoice_id;
		$payment_data->item_name 		= $this->title;
		$payment_data->total			= $this->total;
		$payment_data->currency			= $this->currency;
		$payment_data->expiration_type	= $this->expiration_type;		
		$payment_data->language			= Rb_HelperJoomla::getLanguageCode();
		
		if($this->expiration_type == RB_ECOMMERCE_EXPIRATION_TYPE_RECURRING){
			$payment_data->recurrence_count = $this->recurrence_count;
			
			// get the future total by applying modifiers
			$prices 	= $this->time_price->get('price', array());
			$result 	= array();
			$counter 	= 1;
			$m_helper 	= Rb_EcommerceFactory::getHelper('modifier');
			$master 	= $this->getMasterInvoice();
			$modifiers 	= $master->getModifiers();
			foreach($prices as $price){
				$result[] = $m_helper->getTotalByFrequencyOnInvoiceNumber($modifiers, $price, $counter);;
				$counter++;
			}
			
			$payment_data->price = $result;
			$payment_data->time  = $this->time_price->get('time', array());			
		}
		
		return $payment_data;
	}
	
	private function __requestGetUserData($data)
	{
		// set user data also
		$userid 			 	= $this->getBuyer();
		$user 				 	= Rb_EcommerceFactory::getUser($userid);		
		$user_data 			 	= new stdClass();
		$user_data->id			= $user->get('id');
		$user_data->name 	 	= $user->get('name');
		$user_data->username 	= $user->get('username');
		$user_data->email 	 	= $user->get('email');
		$user_data->ip_address 	= isset($_SERVER['REMOTE_ADDR'])?$_SERVER['REMOTE_ADDR'] : '';
		$user_data->billing_address = new stdClass();
		$user_data->shipping_address = new stdClass();
		
		if(isset($data->billing_address)){
			$user_data->billing_address->to  = $data->billing_address['to'];
			$user_data->billing_address->phone_number  = $data->billing_address['phone_number'];
			$user_data->billing_address->address  = $data->billing_address['address'];
			$user_data->billing_address->zipcode  = $data->billing_address['zipcode'];
			$user_data->billing_address->state  = $data->billing_address['state'];
			$user_data->billing_address->country->isocode2  = $data->billing_address['country']['isocode2'];
			$user_data->billing_address->country->isocode3  = $data->billing_address['country']['isocode3'];
			$user_data->billing_address->city  = $data->billing_address['city'];
			$user_data->billing_address->vat_number  = $data->billing_address['vat_number'];
			
		}
		
		if(isset($data->shipping_address)){
				$user_data->shipping_address->to  = $data->shipping_address['to'];
				$user_data->shipping_address->phone_number  = $data->shipping_address['phone_number'];
				$user_data->shipping_address->address  = $data->shipping_address['address'];
				$user_data->shipping_address->zipcode  = $data->shipping_address['zipcode'];
				$user_data->shipping_address->state  = $data->shipping_address['state'];
				$user_data->shipping_address->country->isocode2  = $data->shipping_address['country']['isocode2'];
				$user_data->shipping_address->country->isocode3  = $data->shipping_address['country']['isocode3'];
				$user_data->shipping_address->city  = $data->shipping_address['city'];
				$user_data->shipping_address->vat_number  = $data->shipping_address['vat_number'];
		}

		return $user_data;
	}
	
	private function __requestGetUrl($data)
	{
		$url = new stdClass();				
		$url->notify_url = isset($data->notify_url) ? $data->notify_url : false;
		$url->cancel_url = isset($data->cancel_url) ? $data->cancel_url : false;
		$url->return_url = isset($data->return_url) ? $data->return_url : false;
		
		return $url;
	}
	
	protected function _requestBuild($data = array())
	{
		$data = (object) $data;
		
		$request = new Rb_EcommerceRequest();
		$request->set('type', 'build');		
		// call on newly created invoice [optional]
		$request->set('payment_data', $this->__requestGetPaymentData());
		$request->set('user_data', $this->__requestGetUserData($data));		
		$request->set('url_data', $this->__requestGetUrl($data));
		$request->set('post_data', (object)$data);
		$request->set('processor_data', $this->getProcessorData());

		$request->set('build_type', isset($data->build_type) ? $data->build_type : Rb_EcommerceRequest::BUILD_TYPE_XML );	
		
		return $this->getProcessor()->request($request);
	}
	
	protected function _requestPayment($data = array())
	{
		if($this->getStatus() == self::STATUS_PAID){	
			$invoice = $this->_createChildInvoice();
		}
		else{
			$invoice = $this;
		}
		
		$request = new Rb_EcommerceRequest();
		$request->set('type', 'payment');		
		// call on newly created invoice [optional]
		$request->set('payment_data', $invoice->__requestGetPaymentData());
		$request->set('user_data', $this->__requestGetUserData($data));		
		$request->set('url_data', $this->__requestGetUrl($data));
		$request->set('post_data', (object)$data);
		$request->set('processor_data', $this->getProcessorData());	
		
		$response = $this->getProcessor()->request($request);
				
		// XITODO : improve
		$request_data = new stdClass();
		$request_data->invoice_id = $invoice->getId();	
		$response->request = $request_data;
		return $response;
	}
	
	protected function _requestCancel($data = array())
	{
		$data = (object) $data;
		
		$request = new Rb_EcommerceRequest();
		$request->set('type', 'cancel');
		// call on newly created invoice [optional]		
		$request->set('user_data', $this->__requestGetUserData($data));		
		$request->set('url_data', $this->__requestGetUrl($data));
		$request->set('post_data', (object)$data);
		$request->set('processor_data', $this->getProcessorData());	
		
		return $this->getProcessor()->request($request);
	}
	
	protected function _requestRefund($data = array())
	{		
		// txn_id  is already set
		if(!isset($data['txn_id'])){
			$transaction = $this->getTransaction('payment_status', Rb_EcommerceResponse::PAYMENT_COMPLETE);
			$data['txn_id'] = $transaction->getGatewayTxnId();						
		}
		
		$request = new Rb_EcommerceRequest();
		$request->set('type', 'refund');		
		$request->set('payment_data', $this->__requestGetPaymentData());		
		$request->set('user_data', $this->__requestGetUserData($data));		
		$request->set('url_data', $this->__requestGetUrl($data));
		$request->set('post_data', (object)$data);
		$request->set('processor_data', $this->getProcessorData());	
		
		$response =  $this->getProcessor()->request($request);
		
		$request_data = new stdClass();
		$request_data->invoice_id = $this->getId();	
		$response->request = $request_data;
		return $response;
	}
	
	public function request($type, $data = array())
	{		
		$func = '_request'.ucfirst($type);		
		return $this->$func($data);
	}
	
	public function process($data)
	{	
		// if invoice id is set in response, then get instance of this invoice
		// other wise work on $invoice	 
		$invoice = isset($data->request->invoice_id) ? Rb_EcommerceInvoice::getInstance($data->request->invoice_id) : $this;
		$response = $invoice->getProcessor()->process($data);
		
		return $this->_process($invoice, $response, $data);
	}
	
	/**
	 * 
	 * Invoke this method when you need to manual paid for particular this invoice
	 * @param Rb_EcommerceResponse $response
	 * @param unknown_type $data
	 * 
	 * @return Rb_EcommerceResponse 
	 */
	public function directPay($bind_data = Array())
	{
		//1#. Create response from $bind_data
		 
		$response = new Rb_EcommerceResponse();
		
		//@TODO:: can we compulsory gateway transaction ID
		
		//gateway_txn_id
		if ( isset($bind_data['gatewaytransaction_id'])) {
			$response->set('txn_id', $bind_data['gatewaytransaction_id']);	
		}
		//gateway_subscr_id
		if ( isset($bind_data['subscr_id'])) {
			$response->set('subscr_id', $bind_data['subscr_id']);	
		}
		//gateway_parent_txn
		if ( isset($bind_data['parent_txn'])) {
			$response->set('parent_txn', $bind_data['parent_txn']);	
		}
		//amount
		if ( isset($bind_data['amount'])) {
			$response->set('amount', $bind_data['amount']);	
		}
		//payment_status
		if ( isset($bind_data['payment_status'])) {
			$response->set('payment_status', $bind_data['payment_status']);	
		}		
		//message
		if ( isset($bind_data['message'])) {
			$response->set('message', $bind_data['message']);	
		}
		//params
		if ( isset($bind_data['params'])) {
			$response->set('params', $bind_data['params']);	
		}
		
		//2#. Process Invoice without Processor calling
		return $this->_process($this, $response, $bind_data);
	}

	/**
	 * 
	 * Invoke to process processor's response on invoice
	 * @param Rb_EcommerceInvoice $invoice
	 * @param Rb_EcommerceResponse $response
	 * @param unknown_type $data
	 * 
	 * @return Rb_EcommerceResponse
	 */
	private function _process(Rb_EcommerceInvoice $invoice, Rb_EcommerceResponse $response, $data )
	{
		$helper = $this->getHelper();
		
		if($response->get('payment_status') == Rb_EcommerceResponse::PAYMENT_COMPLETE){
			$invoice->_process_response_payment_completed($response, $data);
		}
		elseif($response->get('payment_status') == Rb_EcommerceResponse::PAYMENT_REFUND){
			if(!isset($data->request) || !isset($data->request->invoice_id)){
				$txn_id_refunded = $response->get('parent_txn');
				$tranasction_records= Rb_EcommerceFactory::getInstance('transaction', 'model')
											->loadRecords(array('gateway_txn_id' => $txn_id_refunded, 
																'processor_type' => $this->getProcessorType()));
											
				$tranasction_record = array_pop($tranasction_records);		
				$invoice_id 		= $tranasction_record->invoice_id;
				$invoice = Rb_EcommerceInvoice::getInstance($invoice_id);	
			}
			
			$invoice->_process_response_payment_refund($response, $data);
		}
		elseif($response->get('payment_status') == Rb_EcommerceResponse::PAYMENT_PENDING || $response->get('payment_status') == Rb_EcommerceResponse::SUBSCR_START){
			if($this->getStatus() == Rb_EcommerceInvoice::STATUS_DUE){
				$invoice->_process_response_payment_inprocess($response, $data);
			}
		}
		elseif($response->get('payment_status') == Rb_EcommerceResponse::FAIL || $response->get('payment_status') == Rb_EcommerceResponse::PAYMENT_FAIL){
			$invoice->_process_response_payment_due($response, $data);	
		}
		else{		
			$helper->createTransaction($invoice, $response, $data);
		}
		
		
		// XITODO : trigger here according to payment status
		$processor_data = $response->get('processor_data');
		if(!empty($processor_data)){
			$master = $invoice->getMasterInvoice();
			$master->setProcessorData($processor_data)
				 ->save();
		}
				
		return $response;
	}
	
	protected function _process_response_payment_completed($response, $data)	
	{		
		$helper = $this->getHelper();
		// if the invoice is not paid than process it		
		if($this->getStatus() != Rb_EcommerceInvoice::STATUS_PAID ){			
			$transaction = $helper->createTransaction($this, $response, $data);
			
			// XITODO : should we trigger here..???
			$this->set('status', Rb_EcommerceInvoice::STATUS_PAID)
			     ->set('paid_date', new Rb_Date())
				 ->save();

			return $this;
		}
		
		// if the invoice is already paid
		if($this->getExpirationType() === RB_ECOMMERCE_EXPIRATION_TYPE_RECURRING){
			
			// if invoice is master
			if($this->isMaster()){				
				$new_invoice = $this->_createChildInvoice();				
				$transaction = $helper->createTransaction($new_invoice, $response, $data);
			
				// XITODO : should we trigger here..???
				$new_invoice->set('status', Rb_EcommerceInvoice::STATUS_PAID)
					->save();

				return $new_invoice;
			}
						
			// if invoice is not master
			// XITODO : raise error			
		}
	}
	
	protected function _process_response_payment_refund($response, $data)	
	{
		$helper = $this->getHelper();									 	  
		if($this->getStatus() == Rb_EcommerceInvoice::STATUS_PAID ){			
			$transaction = $helper->createTransaction($this, $response, $data);
			
			// XITODO : should we trigger here..???
			$this->set('status', Rb_EcommerceInvoice::STATUS_REFUNDED)
			     ->set('refund_date', new Rb_Date())
			     ->save();

			return $this;
		}
		
		//XITODO : raise error
	}
	
	protected function _createChildInvoice()
	{
		// get number of all paid invoices under this master invoice
		$childinvoices = $this->getChildInvoices();
		$counter = count($childinvoices) + 1 + 1 ;  // plus 1 is for master invoice and plus 1 for next counter

		$invoice_data = $this->toArray();
		// XITODO : generate child invoice serial number
		$unset  = array('status','created_date','paid_date', 'due_date','issue_date', 'refund_date','serial');
		
		foreach ($unset as $value)
		{
			if (isset($invoice_data[$value])){
				unset($invoice_data[$value]);
			}
		}
		
		$invoice_data['master_invoice_id'] = $this->getId();
		$invoice_data['counter'] = $counter;
		$new_invoice = Rb_EcommerceInvoice::getInstance();
		$new_invoice->create($invoice_data, false);
		
		return $new_invoice;
	}
	
	protected function _process_response_payment_inprocess($response, $data)	
	{	
		$transaction = $this->getHelper()->createTransaction($this, $response, $data);
		$this->set('status', Rb_EcommerceInvoice::STATUS_INPROCESS)
		     ->save();

		return $this;
		
	}
	
	protected function _process_response_payment_due($response, $data)	
	{	
		$transaction = $this->getHelper()->createTransaction($this, $response, $data);
		$this->set('status', Rb_EcommerceInvoice::STATUS_DUE)
		     ->save();

		return $this;
		
	}
}
