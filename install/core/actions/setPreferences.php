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

ob_start();
$result = array('success' => false, 'errorMsg' => '');

if (!empty($_SESSION['install'])) {
	$preferences = array(
		'dateFormat' => $_SESSION['install']['dateFormat'], 
		'timeFormat' => $_SESSION['install']['timeFormat'],
		'timeZone' => $_SESSION['install']['timeZone'],
		'weekStart' => (int)$_SESSION['install']['weekStart'],
		'defaultCurrency' => $_SESSION['install']['defaultCurrency'],
		'thousandSeparator' => $_SESSION['install']['thousandSeparator'],
		'decimalMark' => $_SESSION['install']['decimalMark'],
		'language' => $_SESSION['install']['language'],
		'smtpServer' => $_SESSION['install']['smtpServer'],
		'smtpPort' => $_SESSION['install']['smtpPort'],
		'smtpAuth' => (empty($_SESSION['install']['smtpAuth']) || $_SESSION['install']['smtpAuth'] == 'false' || !$_SESSION['install']['smtpAuth'])? false : true,
		'smtpSecurity' => $_SESSION['install']['smtpSecurity'],
		'smtpUsername' => $_SESSION['install']['smtpUsername'],
		'smtpPassword' => $_SESSION['install']['smtpPassword'],
		'outboundEmailFromName' => $_SESSION['install']['outboundEmailFromName'],
		'outboundEmailFromAddress' => $_SESSION['install']['outboundEmailFromAddress'],
		'outboundEmailIsShared' => (empty($_SESSION['install']['smtpAuth']) || $_SESSION['install']['outboundEmailIsShared'] == 'false' || !$_SESSION['install']['smtpAuth'])? false : true,
	);
	$res = $installer->setPreferences($preferences);
	if (!empty($res)) {
		$result['success'] = true;
	}
	else {
		$result['success'] = false;
		$result['errorMsg'] = 'Cannot save preferences';
	}
}
else {
	$result['success'] = false;
	$result['errorMsg'] = 'Cannot save preferences';
}

ob_clean();
echo json_encode($result);
