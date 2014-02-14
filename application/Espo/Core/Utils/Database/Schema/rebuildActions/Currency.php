<?php

namespace Espo\Core\Utils\Database\Schema\rebuildActions;

class Currency extends \Espo\Core\Utils\Database\Schema\BaseRebuildActions
{
	
	public function afterRebuild()
	{	 
		$currencyConfig = $this->getConfig()->get('currency');	
		$currencyConfig['rate'][ $currencyConfig['base'] ] = '1.00';	

		$pdo = $this->getEntityManager()->getPDO();	

		$sql = "TRUNCATE `currency`";
		$pdo->prepare($sql)->execute();

		foreach ($currencyConfig['rate'] as $currencyName => $rate) {

			$sql = "
				INSERT INTO `currency`
				(id, rate)
				VALUES
				(".$pdo->quote($currencyName) . ", " . $pdo->quote($rate) . ")
			";
			$pdo->prepare($sql)->execute();			
		}					
	}	
	
}

