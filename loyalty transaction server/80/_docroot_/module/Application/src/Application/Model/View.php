<?php
namespace Application\Model;

use Zend\View\Model\JsonModel;
class View 
{
	/**
	 * Response Code
	 * @var number
	 */
	protected $_reponseCode = NULL;
	
	/**
	 * Response Message
	 * @var string
	 */
	protected $_reponseMessage = NULL;
	
	/**
	 * Data to be returned
	 * @var array
	 */
	protected $_data = NULL;
	
	/**
	 * Set the response code
	 * @param number $responseCode
	 * @return \Application\Model\View
	 */
	public function setResponseCode($responseCode)
	{
		$this->_reponseCode = $responseCode;
		
		return $this;
	}
	
	/**
	 * Get the response code
	 * @return number
	 */
	public function getResponseCode()
	{
		return $this->_reponseCode;
	}
	
	/**
	 * Set the response message
	 * @param string $responseMessage
	 * @return \Application\Model\View
	 */
	public function setResponseMessage($responseMessage)
	{
		$this->_reponseMessage = $responseMessage;
		
		return $this;
	}
	
	/**
	 * Get the Response Message
	 * @return string
	 */
	public function getResponseMessage()
	{
		return $this->_reponseMessage;
	}
	
	/**
	 * Set the data to be returned
	 * @param array $data
	 * @return \Application\Model\View
	 */
	public function setData($data)
	{
		$this->_data = $data;
		
		return $this;
	}
	
	public function addData($data)
	{
		if (!is_null($this->getData()) && !is_array($this->getData()))
		{
			throw new \Exception('You cannot use addData() when the value previously set is not an array');
		}
		
		if (is_null($this->getData()))
		{
			$this->_data = array();
		}
		
		$this->_data = array_merge($this->_data, $data);
	}
	
	/**
	 * Get the data
	 * @return array
	 */
	public function getData()
	{
		return $this->_data;
	}
	
	/**
	 * Dispatch
	 * @return \Zend\View\Model\JsonModel
	 */
	public function dispatch()
	{
		$array = array(
    		'service' => array(
	    		'responsecode' => $this->getResponseCode(),
	    		'responsemsg' => $this->getResponseMessage(),
    		)
    	);

		// Only provide data key if it's been set
		if (!is_null($this->getData()))
		{
			$array['service']['data'] = $this->getData();
		}
		
		return new JsonModel($array);
	}
}