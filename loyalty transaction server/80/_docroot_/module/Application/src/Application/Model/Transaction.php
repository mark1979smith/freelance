<?php
namespace Application\Model;

use Zend\ServiceManager\ServiceManager;
use Zend\Stdlib\Parameters;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Expression;
use Zend\Debug\Debug;
use Zend\Log\Logger;
use Zend\Log\Writer\Stream;
use Application\Controller\TransactionController;
use Zend\Db\Sql\Predicate\Predicate;

/**
 * This class is anything to do with transactions
 */
class Transaction
{
	const DB_TABLE_TRANSACTIONS			= 'transactions';
	const DB_TABLE_TRANSACTIONS_ITEMS	= 'transactions_items';
	const DB_TABLE_TRANSACTIONS_TENDERS	= 'transactions_tenders';
	const DB_TABLE_CUSTOMERS			= 'customers';
	const MAX_RESULTS = 250;
	
	/**
	 * Create a new Transaction
	 * @param ServiceManager $sm
	 * @param array $data
	 * @param Logger $logger
	 * @throws \Exception
	 * @return boolean
	 */
	public function create(ServiceManager $sm, $data, $logger)
	{
		// Log the request
		$logger->debug('create()');
		
		// Normalise Data
		$data = new Parameters($data);

		// Define new Database call
		$sql = new Sql($sm->get('db'));
		
		// Get the database connection so we can manage the transaction
		$logger->debug('define connection');
		$connection = $sm->get('db')->getDriver()->getConnection();
		
		// Begin Transaction
		$logger->debug('begin transaction');
		$connection->beginTransaction();
		
		try {
			// Insert into transactions table
			$logger->debug('insert into '. self::DB_TABLE_TRANSACTIONS);
			$insert = $sql->insert();
			$insert->into(self::DB_TABLE_TRANSACTIONS)
				->values(array_merge($data->toArray()['Header'], array('datetime' => date('Y-m-d H:i:s', strtotime($data->toArray()['Header']['datetime'])))));
			$statement = $sql->prepareStatementForSqlObject($insert);
			$affectedRows = $statement->execute()->getAffectedRows();
			$logger->debug($affectedRows .' affected rows');
			if ($affectedRows == 0)
			{
				$logger->debug('rollback');
				$connection->rollback();
				return false;
			}
			
			// Insert into transaction items table
			$logger->debug('insert '. count($data->toArray()['Items']) .' rows into '. self::DB_TABLE_TRANSACTIONS_ITEMS);
			foreach($data->toArray()['Items'] as $key => $itemRow)
			{
				$insert = $sql->insert();
				$insert->into(self::DB_TABLE_TRANSACTIONS_ITEMS)
					->values(array_merge($itemRow, array('docno' => $data->toArray()['Header']['docno'], 'row' => $key)));
				$statement = $sql->prepareStatementForSqlObject($insert);
				$affectedRows = $statement->execute()->getAffectedRows();
				$logger->debug($affectedRows . ' affected rows');
				if ($affectedRows == 0)
				{
					$logger->debug('rollback');
					$connection->rollback();
					return false;
				}
			}
			
			// Insert into transaction tenders table
			$logger->debug('insert '. count($data->toArray()['Tenders']) .' rows into '. self::DB_TABLE_TRANSACTIONS_ITEMS);
			foreach($data->toArray()['Tenders'] as $key => $tenderRow)
			{
				$insert = $sql->insert();
				$insert->into(self::DB_TABLE_TRANSACTIONS_TENDERS)
					->values(array_merge($tenderRow, array('docno' => $data->toArray()['Header']['docno'], 'row' => $key)));
				$statement = $sql->prepareStatementForSqlObject($insert);
				$affectedRows = $statement->execute()->getAffectedRows();
				$logger->debug($affectedRows . ' affected rows');
				if ($affectedRows == 0)
				{
					$logger->debug('rollback');
					$connection->rollback();
					return false;
				}
			}
						
			// Commit the transaction
			$logger->debug('commit transaction');
			$connection->commit();
		}
		catch(\Exception $e)
		{
			$logger->crit($e->getMessage());
			$connection->rollback();
			return false;
		}
		
		return true;
	}
	
	/**
	 * Read from the database 
	 * @param ServiceManager $sm
	 * @param string $table
	 * @param array $filter
	 * @param NULL|array $fields
	 * @param number $page
	 * @return \Zend\Db\Adapter\Driver\ResultInterface
	 */
	public function read(ServiceManager $sm, $table, $filter, $fields = NULL, $page = 1)
	{
		// Enforce Array of $data
		$filter = new Parameters($filter);
		
		// Define new Database call
		$sql = new Sql($sm->get('db'));
		
		// Define SQL Statement
		$select = $sql->select();
		$select
			->from($table)
			->where($filter->toArray())
			->limit(self::MAX_RESULTS);
			
		// Add Fields if needed
		if (is_array($fields))
		{
			$select->columns($fields);
		}
		
		// Run Query
		$statement = $sql->prepareStatementForSqlObject($select);
		
		// Return RowSet
		return $statement->execute();
		
	}
	
	public function delete()
	{
		throw new \Exception('Not implemented yet');
	}
	
	/**
	 * Validate the transactions
	 * @param array $data
	 * @param Customer $customer
	 * @param array $responseCodes
	 * @param array $responseMessages
	 * @param View $view
	 * @param Logger $logger
	 * @param ServiceManager $sm
	 */
	public function validate($data, $customer, $responseCodes, $responseMessages, $view, $logger, $sm)
	{
		// Assign Fields
		if (array_key_exists('Header', $data))
			$headers = $data['Header'];
		else 
			$headers = array();
		if (array_key_exists('Items', $data))
			$items = $data['Items'];
		else 
			$items = array();
		if (array_key_exists('Tenders', $data))
			$tenders = $data['Tenders'];
		else 
			$tenders = array();
		
		if(array_key_exists('docno', $headers))
		{
			// Validate Doc No is unique
			$docNo = $this->read($sm, self::DB_TABLE_TRANSACTIONS, array('docno' => $headers['docno']));
			if (count($docNo) > 0)
			{
				$logger->debug('Invoice Number Exists for '. $headers['docno']);
				$view->setResponseCode($responseCodes[TransactionController::RESPONSE_CODE_INVOICE_NUMER_EXISTS])
				->setResponseMessage($responseMessages[TransactionController::RESPONSE_CODE_INVOICE_NUMER_EXISTS]);
				return $view->dispatch();
			}
		}
				
		// Validate Type Exists
		if (!array_key_exists('Type', $headers))
		{
			$logger->debug('Type not set');
			$view->setResponseCode($responseCodes[TransactionController::RESPONSE_CODE_NO_TYPE_MENTIONED])
			->setResponseMessage($responseMessages[TransactionController::RESPONSE_CODE_NO_TYPE_MENTIONED]);
			return $view->dispatch();
		}
		
		// Validate Date/Time
		if (strtotime($headers['datetime']) === false)
		{
			$logger->debug('Date Time cannot be parsed by strtotime()');
			$view->setResponseCode($responseCodes[TransactionController::RESPONSE_CODE_SERVER_ERROR])
			->setResponseMessage($responseMessages[TransactionController::RESPONSE_CODE_SERVER_ERROR]);
			return $view->dispatch();
		}
		
		// Validate Customer Id
		if (strlen($headers['customerid']) == 0)
		{
			$logger->debug('Customer Id is empty');
			$view->setResponseCode($responseCodes[TransactionController::RESPONSE_CODE_SERVER_ERROR])
			->setResponseMessage($responseMessages[TransactionController::RESPONSE_CODE_SERVER_ERROR]);
			return $view->dispatch();
		}
		
		$customerRecord = $customer->read($sm, array('customerid' => $headers['customerid']));
		if (count($customerRecord) == 0)
		{
			$logger->debug('Customer ID: ' . $headers['customerid'] .' does not exist');
			$view->setResponseCode($responseCodes[TransactionController::RESPONSE_CODE_ORDER_CUSTOMER_MISMATCH])
			->setResponseMessage($responseMessages[TransactionController::RESPONSE_CODE_ORDER_CUSTOMER_MISMATCH]);
			return $view->dispatch();
		}
		
		// Validate Mobile Number
		if (strlen($headers['mobileno']) > 0)
		{
			// Check Mobile Number is required length
			if (strlen($headers['mobileno']) <> 10)
			{
				$logger->debug('Mobile Number: '. $headers['mobileno'] .' is not 10 digits');
				$view->setResponseCode($responseCodes[$customer::RESPONSE_CODE_MOBILE_LENGTH_FAIL])
				->setResponseMessage($responseMessages[$customer::RESPONSE_CODE_MOBILE_LENGTH_FAIL]);
				return $view->dispatch();
			}
			// Check Mobile Number is required regexp
			if (preg_match('/^[7-9]/', $headers['mobileno']) === 0)
			{
				$logger->debug('Mobile Number: ' . $headers['mobileno'] .' does not match regexp');
				$view->setResponseCode($responseCodes[$customer::RESPONSE_CODE_MOBILE_REGEXP_FAIL])
				->setResponseMessage($responseMessages[$customer::RESPONSE_CODE_MOBILE_REGEXP_FAIL]);
				return $view->dispatch();
			}
			// Check Mobile Number exists in system
			$mobileNumberRecord = $customer->read($sm, array('mobileno' => $headers['mobileno']));
			if (count($mobileNumberRecord) == 0)
			{
				$logger->debug('Mobile Number: '. $headers['mobileno'] .' does not exist in customer database table');
				$view->setResponseCode($responseCodes[TransactionController::RESPONSE_CODE_ORDER_CUSTOMER_MISMATCH])
				->setResponseMessage($responseMessages[TransactionController::RESPONSE_CODE_ORDER_CUSTOMER_MISMATCH]);
				return $view->dispatch();
			}
		}
		
		// Validate Sales Type
		if (strcasecmp($headers['Type'], 'sales') === 0)
		{
			// Ensure Redeem Points exist
			if ((array_key_exists('redeempoints', $headers) && strlen($headers['redeempoints']) == 0) || !array_key_exists('redeempoints', $headers))
			{
				$logger->debug('Redeem Points is not set or is empty');
				$view->setResponseCode($responseCodes[TransactionController::RESPONSE_CODE_SERVER_ERROR])
				->setResponseMessage($responseMessages[TransactionController::RESPONSE_CODE_SERVER_ERROR]);
				return $view->dispatch();
			}
				
			// Ensure Amount exists
			/*if ((array_key_exists('amount', $headers) && strlen($headers['amount']) == 0) || !array_key_exists('amount', $headers))
			 {
			$view->setResponseCode($responseCodes[TransactionController::RESPONSE_CODE_SERVER_ERROR])
			->setResponseMessage($responseMessages[TransactionController::RESPONSE_CODE_SERVER_ERROR]);
			return $view->dispatch();
			}*/
		}
		
		// Validate OTP
		if (array_key_exists('redeempoints', $headers) && $headers['redeempoints'] == 1)
		{
			if ((!array_key_exists('otp', $headers) && strlen($headers['otp']) <> 6) || !array_key_exists('otp', $headers))
			{
				$logger->debug('OTP either does not exist or is not correct');
				$view->setResponseCode($responseCodes[TransactionController::RESPONSE_CODE_SERVER_ERROR])
				->setResponseMessage($responseMessages[TransactionController::RESPONSE_CODE_SERVER_ERROR]);
				return $view->dispatch();
			}
		}
		
		// Validate Payment mode
		foreach($tenders as $tenderRow)
		{
			if(!in_array($tenderRow['paymentmode'], array(
					'cod', // Cash
					'clp', // Credit Loyalty Points
					'gv', // Gift Voucher
					'gc', // Gift Card
			)))
			{
				$logger->debug('PaymentMode: '. $tenderRow['paymentmode'] .' is not one of COD, CLP, GV, GC');
				$view->setResponseCode($responseCodes[TransactionController::RESPONSE_CODE_SERVER_ERROR])
				->setResponseMessage($responseMessages[TransactionController::RESPONSE_CODE_SERVER_ERROR]);
				return $view->dispatch();
			}
		}
		
		return true;
	}
	
	/**
	 * Update a transaction record
	 * @param ServiceManager $sm
	 * @param string $docno
	 * @param array $data
	 * @throws \Exception
	 * @return \Zend\Db\Adapter\Driver\ResultInterface
	 */
	public function update(ServiceManager $sm, $docno, $data = NULL)
	{
		// Sanity Checking
		if (is_null($data))
		{
			throw new \Exception('Data must be set');
		}
		
		// Enforce array of $data
		$data = new Parameters($data);
	
		// Define Database call
		$sql = new Sql($sm->get('db'));
	
		// Define Update Statement
		$update = $sql->update(self::DB_TABLE_TRANSACTIONS);
	
		// Define Where
		$where = new Predicate();
		$where->equalTo('docno', $docno);
	
		// Define Update Values
		$update->set($data->toArray());
	
		// Add Where
		$update->where($where);
	
		// Run Query
		$statement = $sql->prepareStatementForSqlObject($update);
	
		// Return value of execute()
		return $statement->execute();
	}
	
	
}