<?php
namespace EssentialDots\EdScale\PHPUnit\Backend;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014 Nikola Stojiljkovic, Essential Dots d.o.o. Belgrade
 *  All rights reserved
 *
 *  This script is part of the Typo3 project. The Typo3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

class Module extends \Tx_Phpunit_BackEnd_Module {

	/**
	 * Renders the screens for function "Run tests".
	 *
	 * @return void
	 */
	protected function renderRunTests() {
		$this->getDatabaseConnection()->initializePHPUnitDBConnection();
		parent::renderRunTests();
	}

	/**
	 * @return \EssentialDots\EdScale\Database\DatabaseConnection
	 */
	protected function getDatabaseConnection() {
		return $GLOBALS['TYPO3_DB'];
	}
}