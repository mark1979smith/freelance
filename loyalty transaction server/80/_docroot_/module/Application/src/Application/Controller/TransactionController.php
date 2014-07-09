<?php
namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Debug\Debug;
use Application\Model\Transaction;
use Application\Model\View;
use Zend\I18n\Validator\DateTime;
use Application\Model\Customer;
use Zend\Log\Logger;
use Zend\Log\Writer\Stream;
use Zend\View\Model\JsonModel;
use Zend\Db\Sql\Expression;
class TransactionController extends AbstractActionController 
{
	// General Response Codes
	const RESPONSE_CODE_SUCCESS						= 0;

	// Define Response Codes
	const RESPONSE_CODE_NOT_ENOUGH_PURCHASE_POINTS 	= 1;
	const RESPONSE_CODE_NOT_ENOUGH_LOYALTY_POINTS	= 2;
	const RESPONSE_CODE_INCORRECT_OTP				= 3;
	const RESPONSE_CODE_TRANSACTION_FAILED			= 4;
	const RESPONSE_CODE_UNKNOWN_TRANSACTION_TYPE	= 5;
	const RESPONSE_CODE_NO_TYPE_MENTIONED			= 6;
	const RESPONSE_CODE_ORDER_CUSTOMER_MISMATCH		= 7;
	const RESPONSE_CODE_PRODUCT_NOT_FOUND_IN_INV	= 8;
	const RESPONSE_CODE_SERVER_ERROR				= 9;
	const RESPONSE_CODE_SALES_TRANSACTION_SUCCESS	= 10;
	const RESPONSE_CODE_RETURN_TRANSACTION_SUCCESS	= 11;
	const RESPONSE_CODE_INVOICE_NUMER_EXISTS		= 12;
	
	// History Response Codes
	const RESPONSE_CODE_TRANSACTION_NOT_FOUND		= 13;
	const RESPONSE_CODE_INCOMPLETE_REQUEST			= 14;
	
	// Acknowledge Response Codes
	const RESPONSE_CODE_TRANSACTION_ALREADY_ACK		= 15;
	
	public function indexAction()
	{
		$this->getResponse()->setStatusCode(404);
		return;
	}
	
	/**
	 * Create a new Transaction
	 */
	public function defineAction()
	{
		// Instatiate Transaction Model
		$transaction = new Transaction();
		
		// Instantiate Customer Model
		$customer = new Customer();
		
		// Instatiate View Model
		$view = new View();
		
		// Assign Fields
		$headers = $_REQUEST['data']['Header'];
		$items = $_REQUEST['data']['Items'];
		$tenders = $_REQUEST['data']['Tenders'];
		
		// Assign Response Codes
		$responseCodes = array(
				self::RESPONSE_CODE_SALES_TRANSACTION_SUCCESS	=> 200,
				self::RESPONSE_CODE_RETURN_TRANSACTION_SUCCESS	=> 600,
				self::RESPONSE_CODE_NOT_ENOUGH_PURCHASE_POINTS 	=> 402,
				self::RESPONSE_CODE_NOT_ENOUGH_LOYALTY_POINTS	=> 403,
				self::RESPONSE_CODE_INCORRECT_OTP				=> 404,
				self::RESPONSE_CODE_TRANSACTION_FAILED			=> 406,
				self::RESPONSE_CODE_UNKNOWN_TRANSACTION_TYPE	=> 500,
				self::RESPONSE_CODE_NO_TYPE_MENTIONED			=> 501,
				self::RESPONSE_CODE_ORDER_CUSTOMER_MISMATCH		=> 502,
				self::RESPONSE_CODE_PRODUCT_NOT_FOUND_IN_INV	=> 503,
				self::RESPONSE_CODE_SERVER_ERROR				=> 504,
				self::RESPONSE_CODE_INVOICE_NUMER_EXISTS		=> 410,
		);
		 
		// Assign Response Messages
		$responseMessages = array(
				self::RESPONSE_CODE_SALES_TRANSACTION_SUCCESS	=> 'Sales Transaction successful',
				self::RESPONSE_CODE_RETURN_TRANSACTION_SUCCESS	=> 'Return Transaction successful',
				self::RESPONSE_CODE_NOT_ENOUGH_PURCHASE_POINTS 	=> 'Not  enough purchase points. Minimum 25 purchase points must be present for loyalty points redemption',
				self::RESPONSE_CODE_NOT_ENOUGH_LOYALTY_POINTS	=> 'Not  enough loyalty points. Minimum 25 loyalty points must be present for loyalty points redemption',
				self::RESPONSE_CODE_INCORRECT_OTP				=> 'Incorrect OTP',
				self::RESPONSE_CODE_TRANSACTION_FAILED			=> 'Transaction failed due to server error',
				self::RESPONSE_CODE_UNKNOWN_TRANSACTION_TYPE	=> 'Unknown Transaction type',
				self::RESPONSE_CODE_NO_TYPE_MENTIONED			=> 'No  Type Mentioned',
				self::RESPONSE_CODE_ORDER_CUSTOMER_MISMATCH		=> 'Order-Customer mismatch',
				self::RESPONSE_CODE_PRODUCT_NOT_FOUND_IN_INV	=> 'Product not found in Invoice',
				self::RESPONSE_CODE_SERVER_ERROR				=> 'Server Error',
				self::RESPONSE_CODE_INVOICE_NUMER_EXISTS		=> 'Invoice Number already exists',
		);
		
		// Instantiate Logger
		$logger = new Logger();
		$stream = @fopen('data/logs/Transaction.log', 'a', false);
		if (! $stream) {
			$view->setResponseCode($responseCodes[self::RESPONSE_CODE_SERVER_ERROR])
				->setResponseMessage($responseMessages[self::RESPONSE_CODE_SERVER_ERROR]);
			return $view->dispatch();
		}
		$writer = new Stream('data/logs/Transaction.log');
		$logger->addWriter($writer);
		$logger->debug($_SERVER['REQUEST_URI']);
		
		/**
		 * Start Validation
		 */
		$validationStatus = $transaction->validate($_REQUEST['data'], $customer, $responseCodes, $responseMessages, $view, $logger, $this->getServiceLocator());
		// If validation returns instance of JsonMode, it means we need to stop
		// and show the error message
		if ($validationStatus instanceof JsonModel)
		{
			return $validationStatus;
		}
		// Create the transaction
		$status = $transaction->create($this->getServiceLocator(), $_REQUEST['data'], $logger);
		$logger->debug('Created Transaction - Status is ' . (int) $status);
		if ($status === true)
		{
			// Define Points Earned
			$transactionPoints = 0;
			
			// If a SALE transaction
			if (strcasecmp($headers['Type'], 'sales') === 0)
			{
				$view->setResponseCode($responseCodes[self::RESPONSE_CODE_SALES_TRANSACTION_SUCCESS])
					->setResponseMessage($responseMessages[self::RESPONSE_CODE_SALES_TRANSACTION_SUCCESS]);
				
				$logger->debug('If Sales, we need to add loyalty points');
				// If we get this far - the transaction was successful
				// If Sales we need to add the loyalty points
				if (strcasecmp($headers['Type'], 'sales') === 0)
				{
					// Get existing Loyalty Points
					$customer = new Customer();
					$customerPoints = $customer->read($this->getServiceLocator(), array('customerid' => $headers['customerid']), array('points'));
						
					// Get total of Transaction Points
					$transactionPoints = 0;
					foreach($tenders as $tenderRow)
					{
						$transactionPoints += $tenderRow['amount'];
					}
					
					$transactionPoints = floor($transactionPoints/100);
					
					// Update Customer with transaction points
					try {
						$customer->update($this->getServiceLocator(), $headers['customerid'],
								array(
										'points' => new Expression(
												'(' . $customerPoints->current()['points'] .' + '.
												$transactionPoints . ')'
										)
								)
						);
					}
					catch(\Exception $e)
					{
						$logger->crit($e->getMessage() .' on line '. __LINE__);
						$view->setResponseCode($responseCodes[self::RESPONSE_CODE_SERVER_ERROR])
							->setResponseMessage($responseMessages[self::RESPONSE_CODE_SERVER_ERROR]);
						return $view->dispatch();
					}
				}
			}
			// Else if a RETURN transaction
			elseif (strcasecmp($headers['Type'], 'return') === 0)
			{
				$view->setResponseCode($responseCodes[self::RESPONSE_CODE_RETURN_TRANSACTION_SUCCESS])
					->setResponseMessage($responseMessages[self::RESPONSE_CODE_RETURN_TRANSACTION_SUCCESS]);
			}
			
			if (!is_null($view->getResponseCode()))
			{
				$view->setData(array('docno' => $headers['docno'], 'orderid' => '0', 'pointsearned' => $transactionPoints));
				return $view->dispatch();
			}
		}
		else
		{
			$view->setResponseCode($responseCodes[self::RESPONSE_CODE_SERVER_ERROR])
				->setResponseMessage($responseMessages[self::RESPONSE_CODE_SERVER_ERROR]);
			return $view->dispatch();
		}
	}
	
	public function bulkAction()
	{
		// Instatiate Transaction Model
		$transaction = new Transaction();
		
		// Instantiate Customer Model
		$customer = new Customer();
		
		// Instatiate View Model
		$view = new View();
		
		// Assign Response Codes
		$responseCodes = array(
				self::RESPONSE_CODE_SALES_TRANSACTION_SUCCESS	=> 200,
				self::RESPONSE_CODE_RETURN_TRANSACTION_SUCCESS	=> 600,
				self::RESPONSE_CODE_NOT_ENOUGH_PURCHASE_POINTS 	=> 402,
				self::RESPONSE_CODE_NOT_ENOUGH_LOYALTY_POINTS	=> 403,
				self::RESPONSE_CODE_INCORRECT_OTP				=> 404,
				self::RESPONSE_CODE_TRANSACTION_FAILED			=> 406,
				self::RESPONSE_CODE_UNKNOWN_TRANSACTION_TYPE	=> 500,
				self::RESPONSE_CODE_NO_TYPE_MENTIONED			=> 501,
				self::RESPONSE_CODE_ORDER_CUSTOMER_MISMATCH		=> 502,
				self::RESPONSE_CODE_PRODUCT_NOT_FOUND_IN_INV	=> 503,
				self::RESPONSE_CODE_SERVER_ERROR				=> 504,
				self::RESPONSE_CODE_INVOICE_NUMER_EXISTS		=> 410,
		);
		
		// Assign Response Messages
		$responseMessages = array(
				self::RESPONSE_CODE_SALES_TRANSACTION_SUCCESS	=> 'Bulk Transaction successful',
				self::RESPONSE_CODE_RETURN_TRANSACTION_SUCCESS	=> 'Return Transaction successful',
				self::RESPONSE_CODE_NOT_ENOUGH_PURCHASE_POINTS 	=> 'Not  enough purchase points. Minimum 25 purchase points must be present for loyalty points redemption',
				self::RESPONSE_CODE_NOT_ENOUGH_LOYALTY_POINTS	=> 'Not  enough loyalty points. Minimum 25 loyalty points must be present for loyalty points redemption',
				self::RESPONSE_CODE_INCORRECT_OTP				=> 'Incorrect OTP',
				self::RESPONSE_CODE_TRANSACTION_FAILED			=> 'Transaction failed due to server error',
				self::RESPONSE_CODE_UNKNOWN_TRANSACTION_TYPE	=> 'Unknown Transaction type',
				self::RESPONSE_CODE_NO_TYPE_MENTIONED			=> 'No  Type Mentioned',
				self::RESPONSE_CODE_ORDER_CUSTOMER_MISMATCH		=> 'Order-Customer mismatch',
				self::RESPONSE_CODE_PRODUCT_NOT_FOUND_IN_INV	=> 'Product not found in Invoice',
				self::RESPONSE_CODE_SERVER_ERROR				=> 'Server Error',
				self::RESPONSE_CODE_INVOICE_NUMER_EXISTS		=> 'Invoice Number already exists',
		);
			
		// Instantiate Logger
		$logger = new Logger();
		$stream = @fopen('data/logs/Transaction.log', 'a', false);
		if (! $stream) {
			$view->setResponseCode($responseCodes[self::RESPONSE_CODE_SERVER_ERROR])
			->setResponseMessage($responseMessages[self::RESPONSE_CODE_SERVER_ERROR]);
			return $view->dispatch();
		}
		$writer = new Stream('data/logs/Transaction.log');
		$logger->addWriter($writer);
		$logger->debug($_SERVER['REQUEST_URI']);

		if (!array_key_exists('data', $_REQUEST))
		{
			$view->setResponseCode($responseCodes[self::RESPONSE_CODE_SALES_TRANSACTION_SUCCESS])
				->setResponseMessage($responseMessages[self::RESPONSE_CODE_SALES_TRANSACTION_SUCCESS])
				->setData(array());
			return $view->dispatch();
		}
		
		foreach($_REQUEST['data'] as $data)
		{
			// Assign Fields
			$headers = $data['Header'];
			$tenders = $data['Tenders'];
			
			/**
			 * Start Validation
			 */
			$validationStatus = $transaction->validate($data, $customer, $responseCodes, $responseMessages, $view, $logger, $this->getServiceLocator());
			// If validation returns instance of JsonMode, it means we need to stop
			// and show the error message
			if ($validationStatus instanceof JsonModel)
			{
				return $validationStatus;
			}
			// Create the transaction
			$status = $transaction->create($this->getServiceLocator(), $data, $logger);
			$logger->debug('Created Transaction - Status is ' . (int) $status);
			if ($status === true)
			{
				// Define Points Earned
				$transactionPoints = 0;
				
				// If a SALE transaction
				if (strcasecmp($headers['Type'], 'sales') === 0)
				{
					// Get existing Loyalty Points
					$customer = new Customer();
					$customerPoints = $customer->read($this->getServiceLocator(), array('customerid' => $headers['customerid']), array('points'));
						
					// Get total of Transaction Points
					$transactionPoints = 0;
					foreach($tenders as $tenderRow)
					{
						$transactionPoints += $tenderRow['amount'];
					}
					
					$transactionPoints = floor($transactionPoints/100);
					
					// Update Customer with transaction points
					try {
						$customer->update($this->getServiceLocator(), $headers['customerid'],
								array(
										'points' => new Expression(
												'(' . $customerPoints->current()['points'] .' + '.
												$transactionPoints . ')'
										)
								)
						);
					}
					catch(\Exception $e)
					{
						$logger->crit($e->getMessage() .' on line '. __LINE__);
						$view->setResponseCode($responseCodes[self::RESPONSE_CODE_SERVER_ERROR])
							->setResponseMessage($responseMessages[self::RESPONSE_CODE_SERVER_ERROR]);
						return $view->dispatch();
					}
				}
				// Else if a RETURN transaction
				elseif (strcasecmp($headers['Type'], 'return') === 0)
				{
					$view->setResponseCode($responseCodes[self::RESPONSE_CODE_RETURN_TRANSACTION_SUCCESS])
						->setResponseMessage($responseMessages[self::RESPONSE_CODE_RETURN_TRANSACTION_SUCCESS]);
				}
				
				if (!is_null($view->getResponseCode()))
				{
					$view->addData(array('docno' => $headers['docno'], 'orderid' => '0', 'pointsearned' => $transactionPoints));
					return $view->dispatch();
				}
			}
			else
			{
				$view->setResponseCode($responseCodes[self::RESPONSE_CODE_SERVER_ERROR])
					->setResponseMessage($responseMessages[self::RESPONSE_CODE_SERVER_ERROR]);
				return $view->dispatch();
			}
		}
	}
	
	public function retrieveAction()
	{
		
	}
	
	public function historyAction()
	{
		// Instatiate Transaction Model
		$transaction = new Transaction();
		
		// Instantiate Customer Model
		$customer = new Customer();
		
		// Instatiate View Model
		$view = new View();
		
		// Assign Fields
		$mobileNumber = $_REQUEST['mobileno'];
		$customerId = $_REQUEST['customerid'];
		
		// Assign Response Codes
		$responseCodes = array(
			self::RESPONSE_CODE_SUCCESS	=> 200,
			self::RESPONSE_CODE_TRANSACTION_NOT_FOUND => 404,
			self::RESPONSE_CODE_INCOMPLETE_REQUEST => 406
		);
		
		// Assign Response Messages
		$responseMessages = array(
			self::RESPONSE_CODE_SUCCESS => 'Success',
			self::RESPONSE_CODE_TRANSACTION_NOT_FOUND => 'Transaction does not exists',
			self::RESPONSE_CODE_INCOMPLETE_REQUEST => 'Incomplete request!'
		);
		
		// Instantiate Logger
		$logger = new Logger();
		$stream = @fopen('data/logs/Transaction.log', 'a', false);
		if (! $stream) {
			$view->setResponseCode($responseCodes[self::RESPONSE_CODE_SERVER_ERROR])
			->setResponseMessage($responseMessages[self::RESPONSE_CODE_SERVER_ERROR]);
			return $view->dispatch();
		}
		$writer = new Stream('data/logs/Transaction.log');
		$logger->addWriter($writer);
		$logger->debug($_SERVER['REQUEST_URI']);
		
		if (strlen($customerId) == 0)
		{
			$view->setResponseCode($responseCodes[self::RESPONSE_CODE_INCOMPLETE_REQUEST])
				->setResponseMessage($responseMessages[self::RESPONSE_CODE_INCOMPLETE_REQUEST])
				->setData(array('customerid'));
			return $view->dispatch();
		}
		
		$transactions = $transaction->read($this->getServiceLocator(), Transaction::DB_TABLE_TRANSACTIONS, array(
			'mobileno' => $mobileNumber,
			'customerid' => $customerId
		));
		$view->setData(array(
			'mobileno' => $mobileNumber,
			'customerid' => $customerId,
			'noofresults' => count($transactions)
		));
		if (count($transactions) > 0)
		{
			$view->setResponseCode($responseCodes[self::RESPONSE_CODE_SUCCESS])
			->setResponseMessage($responseMessages[self::RESPONSE_CODE_SUCCESS])
			->addData(array('page' => 1));
		}
		else 
		{
			$view->setResponseCode($responseCodes[self::RESPONSE_CODE_TRANSACTION_NOT_FOUND])
			->setResponseMessage($responseMessages[self::RESPONSE_CODE_TRANSACTION_NOT_FOUND])
			->addData(array('page' => 0));
		}
		
		return $view->dispatch();
	}
	
	public function acknowledgeAction()
	{
		// Instatiate Transaction Model
		$transaction = new Transaction();
		
		// Instantiate Customer Model
		$customer = new Customer();
		
		// Instatiate View Model
		$view = new View();
		
		// Assign Fields
		$docNo = $_REQUEST['docno'];
		$status = $_REQUEST['status'];
		$datetime = $_REQUEST['datetime'];
		
		// Assign Response Codes
		$responseCodes = array(
			self::RESPONSE_CODE_SUCCESS	=> 200,
			self::RESPONSE_CODE_TRANSACTION_ALREADY_ACK => 406,
		);
		
		// Assign Response Messages
		$responseMessages = array(
			self::RESPONSE_CODE_SUCCESS => 'Success',
			self::RESPONSE_CODE_TRANSACTION_ALREADY_ACK => 'Transaction is already acknowledged as complete',
		);
		
		// Instantiate Logger
		$logger = new Logger();
		$stream = @fopen('data/logs/Transaction.log', 'a', false);
		if (! $stream) {
			$view->setResponseCode($responseCodes[self::RESPONSE_CODE_SERVER_ERROR])
			->setResponseMessage($responseMessages[self::RESPONSE_CODE_SERVER_ERROR]);
			return $view->dispatch();
		}
		$writer = new Stream('data/logs/Transaction.log');
		$logger->addWriter($writer);
		$logger->debug($_SERVER['REQUEST_URI']);
		
		$transactions = $transaction->read($this->getServiceLocator(), Transaction::DB_TABLE_TRANSACTIONS, array(
			'docno' => $docNo,
		));
		
		$logger->debug('Found '. count($transactions) .' results');
		if (count($transactions) == 0)
		{
			$view->setResponseCode($responseCodes[self::RESPONSE_CODE_TRANSACTION_ALREADY_ACK])
				->setResponseMessage($responseMessages[self::RESPONSE_CODE_TRANSACTION_ALREADY_ACK]);
			
			return $view->dispatch();
		}
		else if ($transactions->current()['ack_status'] == 1)
		{
			$view->setResponseCode($responseCodes[self::RESPONSE_CODE_TRANSACTION_ALREADY_ACK])
				->setResponseMessage($responseMessages[self::RESPONSE_CODE_TRANSACTION_ALREADY_ACK]);
				
			return $view->dispatch();
		}
		else if ($transactions->current()['ack_status'] == 0)
		{
			$transaction->update($this->getServiceLocator(), $docNo, array(
				'ack_status' => $status,
				'ack_datetime' => date('Y-m-d H:i:s', strtotime($datetime))
			));
			
			$view->setResponseCode($responseCodes[self::RESPONSE_CODE_SUCCESS])
				->setResponseMessage($responseMessages[self::RESPONSE_CODE_SUCCESS]);
			
			return $view->dispatch();
		}
		
	}
}

?>