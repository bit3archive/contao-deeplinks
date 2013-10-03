<?php

/**
 * Deeplinks extension for Contao Open Source CMS
 * Copyright (C) 2013 Tristan Lins
 *
 * PHP version 5
 *
 * @copyright  bit3 UG 2013
 * @author     Tristan Lins <tristan.lins@bit3.de>
 * @package    avisota
 * @license    LGPL-3.0+
 * @filesource
 */

$GLOBALS['TL_HOOKS']['initializeDependencyContainer'][] = array(
	'Bit3\Contao\Deeplinks\Deeplinks',
	'hookInitializeDependencyContainer'
);
$GLOBALS['TL_HOOKS']['getUserNavigation'][]             = array(
	'Bit3\Contao\Deeplinks\Deeplinks',
	'hookGetUserNavigation'
);
