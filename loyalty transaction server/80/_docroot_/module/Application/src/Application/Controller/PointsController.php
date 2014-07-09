<?php
namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Application\Model\Customer;
use Application\Model\View;
use Zend\I18n\Validator\Float;
use Zend\Db\Sql\Expression;
class PointsController extends AbstractActionController 
{
	// General Response Codes
	const RESPONSE_CODE_SUCCESS 				= 0;
	const RESPONSE_CODE_USER_DOES_NOT_EXIST		= 1;
	
	// Is Redeemed Response Codes
	const RESPONSE_CODE_INSUFFICIENT_POINTS		= 2;
	const RESPONSE_CODE_MIN_THRESHOLD_LIMIT		= 3;
	
	// Define minimum available number of points to redeem
	const MIN_REDEEM_POINTS = 25;
	
	public function indexAction()
	{
		$this->getResponse()->setStatusCode(404);
		return;
	}
	
	/**
	 * Search for points by mobile number
	 * @return \Zend\View\Model\JsonModel
	 */
	public function bymobileAction()
	{
		// Instatiate Customer Model
		$customer = new Customer();
		 
		// Instatiate View Model
		$view = new View();
		
		// Assign Field
		$mobileno 		= (array_key_exists('mobileno', $_REQUEST) ? trim($_REQUEST['mobileno']) : '');
		
		// Assign Response Codes
		$responseCodes = array(
			self::RESPONSE_CODE_SUCCESS				=> 200,
			self::RESPONSE_CODE_USER_DOES_NOT_EXIST	=> 404
		);
		
		// Assign Response Messages
		$responseMessages = array(
			self::RESPONSE_CODE_SUCCESS					=> 'Success',
			self::RESPONSE_CODE_USER_DOES_NOT_EXIST		=> 'User Not Found',
		);
		
		/**
		 * Check Required Fields
		 */
		// Check Mobile Number has been entered
		if (strlen($mobileno) == 0)
		{
			$view->setResponseCode($responseCodes[self::RESPONSE_CODE_USER_DOES_NOT_EXIST])
				->setResponseMessage($responseMessages[self::RESPONSE_CODE_USER_DOES_NOT_EXIST]);
			return $view->dispatch();
		}
		
		// Get Customer
		$results = $customer->read(
				$this->getServiceLocator(), 
				array('mobileno' => $mobileno), 
				array('points', 'lpmonetary', 'socialpoints', 'spmonetary')
		);
		if (count($results) > 0)
		{
			$view->setResponseCode($responseCodes[self::RESPONSE_CODE_SUCCESS])
				->setResponseMessage($responseMessages[self::RESPONSE_CODE_SUCCESS])
				->setData($results->current());
			return $view->dispatch();
		}
		else 
		{
			$view->setResponseCode($responseCodes[self::RESPONSE_CODE_USER_DOES_NOT_EXIST])
				->setResponseMessage($responseMessages[self::RESPONSE_CODE_USER_DOES_NOT_EXIST]);
			return $view->dispatch();
		}
	}
	
	/**
	 * Check if a customer can redeem a certain number of points
	 * @return \Zend\View\Model\JsonModel
	 */
	public function isredeemedAction()
	{
		// Instatiate Customer Model
		$customer = new Customer();
			
		// Instatiate View Model
		$view = new View();
		
		// Assign Field
		$mobileno 		= (array_key_exists('mobileno', $_REQUEST) ? trim($_REQUEST['mobileno']) : '');
		$amount			= (array_key_exists('amount', $_REQUEST) ? trim($_REQUEST['amount']) : '');
		
		// Assign Response Codes
		$responseCodes = array(
				self::RESPONSE_CODE_SUCCESS				=> 200,
				self::RESPONSE_CODE_USER_DOES_NOT_EXIST	=> 404,
				self::RESPONSE_CODE_INSUFFICIENT_POINTS	=> 406,
				self::RESPONSE_CODE_MIN_THRESHOLD_LIMIT	=> 406,
		);
		
		// Assign Response Messages
		$responseMessages = array(
				self::RESPONSE_CODE_SUCCESS					=> 'Success',
				self::RESPONSE_CODE_USER_DOES_NOT_EXIST		=> 'User Not Found',
				self::RESPONSE_CODE_INSUFFICIENT_POINTS		=> 'Insufficient points for redemption!',
				self::RESPONSE_CODE_MIN_THRESHOLD_LIMIT		=> 'Minimum '. self::MIN_REDEEM_POINTS .' points used for redeem!',
		);
		
		// Validate Amount
		$floatValidator = new Float();
		if ($floatValidator->isValid($amount) !== true)
		{
			$view->setResponseCode($responseCodes[self::RESPONSE_CODE_MIN_THRESHOLD_LIMIT])
				->setResponseMessage($responseMessages[self::RESPONSE_CODE_MIN_THRESHOLD_LIMIT]);
			return $view->dispatch();
		}
		
		// Get Customer
		$results = $customer->read(
				$this->getServiceLocator(), 
				array('mobileno' => $mobileno), 
				array(
						'availablepoints' => new Expression('(points + socialpoints)'), 
						'availablemonetaryvalue' => new Expression('(points + socialpoints)'), 
						'canredeem' => new Expression('IF((points + socialpoints) > GREATEST('. self::MIN_REDEEM_POINTS .','. $amount .'),1,0)')
				)
		);
		if (count($results) > 0) 
		{
			$view->setResponseCode($responseCodes[self::RESPONSE_CODE_SUCCESS])
				->setResponseMessage($responseMessages[self::RESPONSE_CODE_SUCCESS])
				->setData($results->current());
			return $view->dispatch();
		}
		else
		{
			$view->setResponseCode($responseCodes[self::RESPONSE_CODE_USER_DOES_NOT_EXIST])
				->setResponseMessage($responseMessages[self::RESPONSE_CODE_USER_DOES_NOT_EXIST]);
			return $view->dispatch();
		}
	}
}

?>