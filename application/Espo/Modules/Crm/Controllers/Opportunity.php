<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014  Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 ************************************************************************/ 

namespace Espo\Modules\Crm\Controllers;

class Opportunity extends \Espo\Core\Controllers\Record
{
	public function actionReportByLeadSource($params, $data, $request)
	{
		$dateFrom = $request->get('dateFrom');
		$dateTo = $request->get('dateTo');
		
		$pdo = $this->getEntityManager()->getPDO();
		
		$sql = "
			SELECT opportunity.lead_source AS `leadSource`, SUM(opportunity.amount * currency.rate * opportunity.probability / 100) as `amount`
			FROM opportunity
			JOIN currency ON currency.id = opportunity.amount_currency
			WHERE 
				opportunity.deleted = 0 AND
				opportunity.close_date >= ".$pdo->quote($dateFrom)." AND
				opportunity.close_date < ".$pdo->quote($dateTo)." AND
				opportunity.stage <> 'Closed Lost' AND
				opportunity.lead_source <> ''
			GROUP BY opportunity.lead_source				
		";
		
		$sth = $pdo->prepare($sql);
		$sth->execute();
		
		$rows = $sth->fetchAll(\PDO::FETCH_ASSOC);
		
		$result = array();
		foreach ($rows as $row) {
			$result[$row['leadSource']] = floatval($row['amount']);
		}		
		return $result;
	}
	
	public function actionReportByStage($params, $data, $request)
	{
		$dateFrom = $request->get('dateFrom');
		$dateTo = $request->get('dateTo');
		
		$pdo = $this->getEntityManager()->getPDO();
		
		$sql = "
			SELECT opportunity.stage AS `stage`, SUM(opportunity.amount * currency.rate) as `amount`
			FROM opportunity
			JOIN currency ON currency.id = opportunity.amount_currency
			WHERE 
				opportunity.deleted = 0 AND
				opportunity.close_date >= ".$pdo->quote($dateFrom)." AND
				opportunity.close_date < ".$pdo->quote($dateTo)." AND
				opportunity.stage <> 'Closed Lost'
			GROUP BY opportunity.lead_source
			ORDER BY `amount` DESC			
		";
		
		$sth = $pdo->prepare($sql);
		$sth->execute();
		
		$rows = $sth->fetchAll(\PDO::FETCH_ASSOC);
		
		$result = array();
		foreach ($rows as $row) {
			$result[$row['stage']] = floatval($row['amount']);
		}	
				
		return $result;
	}
	
	public function actionReportSalesByMonth($params, $data, $request)
	{
		$dateFrom = $request->get('dateFrom');
		$dateTo = $request->get('dateTo');
				
		$pdo = $this->getEntityManager()->getPDO();
		
		$sql = "
			SELECT DATE_FORMAT(opportunity.close_date, '%Y-%m') AS `month`, SUM(opportunity.amount * currency.rate) as `amount`
			FROM opportunity
			JOIN currency ON currency.id = opportunity.amount_currency
			WHERE 
				opportunity.deleted = 0 AND
				opportunity.close_date >= ".$pdo->quote($dateFrom)." AND
				opportunity.close_date < ".$pdo->quote($dateTo)." AND
				opportunity.stage = 'Closed Won'
			
			GROUP BY DATE_FORMAT(opportunity.close_date, '%Y-%m')
			ORDER BY opportunity.close_date						
		";
		
		$sth = $pdo->prepare($sql);
		$sth->execute();
		
		$rows = $sth->fetchAll(\PDO::FETCH_ASSOC);
		
		$result = array();
		foreach ($rows as $row) {
			$result[$row['month']] = floatval($row['amount']);
		}	
				
		return $result;
		
	}
	
	public function actionReportSalesPipeline($params, $data, $request)
	{
		$dateFrom = $request->get('dateFrom');
		$dateTo = $request->get('dateTo');
		
		return $this->getService('Opportunity')->reportSalesPipeline($dateFrom, $dateTo);
	}
}

