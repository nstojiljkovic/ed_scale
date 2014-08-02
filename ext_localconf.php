<?php

if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['TYPO3\\CMS\\Core\\Database\\DatabaseConnection']['className'] = 'EssentialDots\\EdScale\\Database\\DatabaseConnection';
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['t3lib_DB']['className'] = 'EssentialDots\\EdScale\\Database\\DatabaseConnection';
