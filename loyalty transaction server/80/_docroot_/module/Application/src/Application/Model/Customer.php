<?php

namespace Application\Model;

use Zend\ServiceManager\ServiceManager;
use Zend\Stdlib\Parameters;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Predicate\Predicate;
use Zend\Debug\Debug;
/**
 * This class is to do anything related to the customer
 * 
 * @author Mark
 *
 */
class Customer 
{
	const DB_TABLE = 'customers';
	const MAX_RESULTS = 250;
	
	/**
	 * Create a customer record
	 * @param ServiceManager $sm
	 * @param array $data
	 * @return number
	 */
	public function create(ServiceManager $sm, $data)
	{
		// Enforce Array of $data
		$data = new Parameters($data);
		
		// Define new Database call
		$sql = new Sql($sm->get('db'));
		
		// Define Insert Statement
		$insert = $sql->insert();
		$insert
			->into(self::DB_TABLE)
			->values($data->toArray());
		
		// Define Statement
		$statement = $sql->prepareStatementForSqlObject($insert);
		
		// Define Affected Rows
		$affectedRows = 0;
		
		// Ensure an insert has been done
		try {
		    $affectedRows = $statement->execute()->getAffectedRows();
		} catch (\Exception $e) {
		    die('Error: ' . $e->getMessage());
		}
		// Otherwise throw error
		if (empty($affectedRows)) {
		    die('Zero rows affected');
		}
		
		// Return primary key of inserted record
		return $sm->get('db')->getDriver()->getLastGeneratedValue();
	}
	
	/**
	 * Get a customer record
	 * Provide filter to narrow down the results
	 * @param ServiceManager $sm
	 * @param array $filter
	 * @return \Zend\Db\Adapter\Driver\ResultInterface
	 */
	public function read(ServiceManager $sm, $filter, $fields = NULL)
	{
		// Enforce Array of $data
		$filter = new Parameters($filter);
		
		// Define new Database call
		$sql = new Sql($sm->get('db'));
		
		// Define SQL Statement
		$select = $sql->select();
		$select
			->from(self::DB_TABLE)
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
	
	public function update(ServiceManager $sm, $customerid, $data = NULL)
	{
		// Sanity Checking
		if (is_null($data))
		{
			throw new \Exception('Data must be set');
		}
		
		if ((int) $customerid == 0)
		{
			throw new \Exception('Customer Id provided is not correct');
		}
		
		// Enforce array of $data
		$data = new Parameters($data);
		
		// Define Database call
		$sql = new Sql($sm->get('db'));
		
		// Define Update Statement
		$update = $sql->update(self::DB_TABLE);
		
		// Define Where
		$where = new Predicate();
		$where->equalTo('customerid', $customerid);
		
		// Define Update Values
		$update->set($data->toArray());
		
		// Add Where
		$update->where($where);
		
		// Run Query
		$statement = $sql->prepareStatementForSqlObject($update);
		
		// Return value of execute()
		return $statement->execute();
	}
	
	public function delete()
	{
		throw new \Exception('Not implemented yet');
	}
}

?>