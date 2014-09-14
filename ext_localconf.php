<?php

if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['TYPO3\\CMS\\Core\\Database\\DatabaseConnection']['className'] = 'EssentialDots\\EdScale\\Database\\DatabaseConnection';
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['t3lib_DB']['className'] = 'EssentialDots\\EdScale\\Database\\DatabaseConnection';

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['Tx_Phpunit_TestRunner_CliTestRunner']['className'] = 'EssentialDots\\EdScale\\PHPUnit\\TestRunner\\CliTestRunner';
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['Tx_Phpunit_BackEnd_Module']['className'] = 'EssentialDots\\EdScale\\PHPUnit\\Backend\\Module';
