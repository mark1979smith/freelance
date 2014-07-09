<?php
namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Validator\EmailAddress;
use Application\Model\Customer;
use Application\Model\View;

class CustomerController extends AbstractActionController
{
	// General Response Codes
	const RESPONSE_CODE_SUCCESS 				= 0;
	
	// Create Custome Response Codes
	const RESPONSE_CODE_USER_EXISTS_MOBILENO	= 1;
	const RESPONSE_CODE_USER_EXISTS_EMAIL		= 2;
	const RESPONSE_CODE_MOBILE_LENGTH_FAIL 		= 3;
	const RESPONSE_CODE_MOBILE_REGEXP_FAIL		= 4;
	const RESPONSE_CODE_EMAIL_LENGTH_FAIL		= 5;
	const RESPONSE_CODE_EMAIL_REGEXP_FAIL		= 6;
	
	// Retrieve Customer Response Codes
	const RESPONSE_CODE_NO_INPUT				= 7;
	const RESPONSE_CODE_USER_DOES_NOT_EXIST		= 8;
	
    public function indexAction()
    {
        $this->getResponse()->setStatusCode(404);
        return;
    }
    
    /**
     * This is used to create a new customer
     * Checks are made to ensure the following:
     *  * Mobile Number must be 10 digits
     *  * Mobile Number must start with a 9, 8 or 7
     *  * Email Address must be valid
     *  * Another User must not exist with the same Mobile Number
     *  * Another User must not exist with the same Email Address
     */
    public function createAction()
    {
    	// Instatiate Customer Model
    	$customer = new Customer();
    	
    	// Instatiate View Model
    	$view = new View(); 
    	 
    	// Assign Fields
    	$fname 		= (array_key_exists('fname', $_REQUEST) ? trim($_REQUEST['fname']) : '');
    	$lname 		= (array_key_exists('lname', $_REQUEST) ? trim($_REQUEST['lname']) : '');
    	$email 		= (array_key_exists('email', $_REQUEST) ? trim($_REQUEST['email']) : '');
    	$mobileno	= (array_key_exists('mobileno', $_REQUEST) ? trim($_REQUEST['mobileno']) : '');
    	
    	// Assign Response Codes
    	$responseCodes = array(
    		self::RESPONSE_CODE_SUCCESS 				=> 200,
    		self::RESPONSE_CODE_USER_EXISTS_MOBILENO 	=> 406,
    		self::RESPONSE_CODE_USER_EXISTS_EMAIL		=> 407,
    		self::RESPONSE_CODE_MOBILE_LENGTH_FAIL		=> 411,
    		self::RESPONSE_CODE_MOBILE_REGEXP_FAIL		=> 412,
    		self::RESPONSE_CODE_EMAIL_LENGTH_FAIL		=> 413,
    		self::RESPONSE_CODE_EMAIL_REGEXP_FAIL		=> 413, 
    	);
    	
    	// Assign Response Messages
    	$responseMessages = array(
    		self::RESPONSE_CODE_SUCCESS					=> 'Success',
    		self::RESPONSE_CODE_MOBILE_LENGTH_FAIL		=> 'Invalid Mobile, not 10 digits',
    		self::RESPONSE_CODE_MOBILE_REGEXP_FAIL		=> 'Invalid Mobile, not starting with 9, 8, 7',
    		self::RESPONSE_CODE_EMAIL_LENGTH_FAIL		=> 'Invalid email address',
    		self::RESPONSE_CODE_EMAIL_REGEXP_FAIL		=> 'Invalid email address',
    		self::RESPONSE_CODE_USER_EXISTS_EMAIL		=> 'User exists with same email',
    		self::RESPONSE_CODE_USER_EXISTS_MOBILENO	=> 'User exists with same mobile'
    	);
    	
    	/**
    	 * Check Required Fields
    	 */
    	// Check Email Address has been entered 
    	if (strlen($email) == 0)
    	{
    		$view->setResponseCode($responseCodes[self::RESPONSE_CODE_EMAIL_LENGTH_FAIL])
    			->setResponseMessage($responseMessages[self::RESPONSE_CODE_EMAIL_LENGTH_FAIL]);
    		return $view->dispatch();
    	}
    	// Check Mobile Number has been entered
    	if (strlen($mobileno) == 0)
    	{
    		$view->setResponseCode($responseCodes[self::RESPONSE_CODE_MOBILE_LENGTH_FAIL])
    			->setResponseMessage($responseMessages[self::RESPONSE_CODE_MOBILE_LENGTH_FAIL]);
    		return $view->dispatch();
    	}
    	
    	/**
    	 * Check data entered is correct
    	 */
    	// Check Email Address format
    	$emailAddressValidator = new EmailAddress();
    	if ($emailAddressValidator->isValid($email) !== true)
    	{
    		$view->setResponseCode($responseCodes[self::RESPONSE_CODE_EMAIL_REGEXP_FAIL])
    			->setResponseMessage($responseMessages[self::RESPONSE_CODE_EMAIL_REGEXP_FAIL]);
    		return $view->dispatch();
    	}
    	// Check Mobile Number is required length
    	if (strlen($mobileno) <> 10)
    	{
    		$view->setResponseCode($responseCodes[self::RESPONSE_CODE_MOBILE_LENGTH_FAIL])
    			->setResponseMessage($responseMessages[self::RESPONSE_CODE_MOBILE_LENGTH_FAIL]);
    		return $view->dispatch();
    	}
    	// Check Mobile Number is required regexp
    	if (preg_match('/^[7-9]/', $mobileno) === 0)
    	{
			$view->setResponseCode($responseCodes[self::RESPONSE_CODE_MOBILE_REGEXP_FAIL])
				->setResponseMessage($responseMessages[self::RESPONSE_CODE_MOBILE_REGEXP_FAIL]);
    		return $view->dispatch();
    	}
    	// Ensure same email address is not in the database
    	$existingUser = $customer->read($this->getServiceLocator(), array('email' => $email));
    	if (count($existingUser) > 0)
    	{
    		$view->setResponseCode($responseCodes[self::RESPONSE_CODE_USER_EXISTS_EMAIL])
    			->setResponseMessage($responseMessages[self::RESPONSE_CODE_USER_EXISTS_EMAIL])
    			->setData(array('customerid' => $existingUser->current()['customerid']));
    		return $view->dispatch();
    	}
    	// Ensure same mobile number is not in the database
    	$existingUser = $customer->read($this->getServiceLocator(), array('mobileno' => $mobileno));
    	if (count($existingUser) > 0)
    	{
    		$view->setResponseCode($responseCodes[self::RESPONSE_CODE_USER_EXISTS_MOBILENO])
    			->setResponseMessage($responseMessages[self::RESPONSE_CODE_USER_EXISTS_MOBILENO])
    			->setData(array('customerid' => $existingUser->current()['customerid']));
    		return $view->dispatch();
    	}
    	 
    	// Save Customer
    	$customerId = $customer->create($this->getServiceLocator(), array(
    		'fname' 	=> $fname,
    		'lname' 	=> $lname,
    		'email' 	=> $email,
    		'mobileno' 	=> $mobileno
    	));
    	
    	// Get Customer
    	$results = $customer->read($this->getServiceLocator(), array('customerid' => $customerId), array('customerid'));
    	
    	$view->setResponseCode($responseCodes[self::RESPONSE_CODE_SUCCESS])
    		->setResponseMessage($responseMessages[self::RESPONSE_CODE_SUCCESS])
    		->setData($results->current());
    	return $view->dispatch();
    }
    
    public function retrieveAction()
    {
    	// Instatiate Customer Model
    	$customer = new Customer();
    	 
    	// Instatiate View Model
    	$view = new View();
    	
    	// Assign Fields
    	$email 		= (array_key_exists('email', $_REQUEST) ? trim($_REQUEST['email']) : '');
    	$mobileno	= (array_key_exists('mobileno', $_REQUEST) ? trim($_REQUEST['mobileno']) : '');
    	 
    	// Assign Response Codes
    	$responseCodes = array(
    		self::RESPONSE_CODE_SUCCESS 				=> 200,
    		self::RESPONSE_CODE_USER_DOES_NOT_EXIST		=> 404,
    		self::RESPONSE_CODE_NO_INPUT				=> 406,
    	);
    	
    	// Assign Response Messages
    	$responseMessages = array(
    		self::RESPONSE_CODE_SUCCESS 				=> 'Success',
    		self::RESPONSE_CODE_USER_DOES_NOT_EXIST		=> 'User does not exist',
    		self::RESPONSE_CODE_NO_INPUT				=> 'No inputs',
    	);
    	
    	/**
    	 * Check Required Fields
    	 */
    	// Check at least one piece of data exists
    	if (strlen($email) == 0 && strlen($mobileno) == 0) 
    	{
    		$view->setResponseCode($responseCodes[self::RESPONSE_CODE_NO_INPUT])
    			->setResponseMessage($responseMessages[self::RESPONSE_CODE_NO_INPUT]);
    		return $view->dispatch();
    	}
    	
    	$filter = array();
    	// Check is user exists
    	if(strlen($email) > 0)
    	{
    		$filter['email'] = $email;
    	}
    	if (strlen($mobileno) > 0)
    	{
    		$filter['mobileno'] = $mobileno;
    	}
    	
    	$results = $customer->read($this->getServiceLocator(), $filter);
    	// if we have found a match
    	if (count($results) > 0)
    	{
	    	$view->setResponseCode($responseCodes[self::RESPONSE_CODE_SUCCESS])
		    	->setResponseMessage($responseMessages[self::RESPONSE_CODE_SUCCESS])
		    	->setData($results->current());
	    	return $view->dispatch();
    	}
    	else
    	{
    		return $view->setResponseCode($responseCodes[self::RESPONSE_CODE_USER_DOES_NOT_EXIST])
    			->setResponseMessage($responseMessages[self::RESPONSE_CODE_USER_DOES_NOT_EXIST])
    			->dispatch();
    	}
    }
}
