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

namespace Bit3\Contao\Deeplinks;

use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Manage deep links in the contao backend menu.
 */
class Deeplinks extends \BackendModule
{
	/**
	 * Initialize the deeplinks.
	 *
	 * @SuppressWarnings(PHPMD.Superglobals)
	 * @SuppressWarnings(PHPMD.CamelCaseVariableName)
	 */
	static public function hookInitializeDependencyContainer()
	{
		/** @var EventDispatcher $eventDispatcher */
		$eventDispatcher = $GLOBALS['container']['event-dispatcher'];
		$eventDispatcher->dispatch('deeplinks-create');

		foreach ($GLOBALS['BE_MOD'] as $groupName => $group) {
			foreach ($group as $moduleName => $module) {
				if (isset($module['deeplink']) && !isset($module['callback'])) {
					$GLOBALS['BE_MOD'][$groupName][$moduleName]['callback'] = 'Bit3\Contao\Deeplinks\Deeplinks';
				}
			}
		}
	}

	/**
	 * Set the "active" css class to deeplink entries.
	 *
	 * @param array $navigation
	 * @param bool  $showAll
	 *
	 * @return array
	 */
	public function hookGetUserNavigation(array $navigation, $showAll)
	{
		if (TL_MODE != 'BE' || $showAll) {
			return $navigation;
		}

		$active          = null;
		$currentDeeplink = null;
		$currentPriority = -10;

		foreach ($navigation as $groupName => $group) {
			foreach ($group['modules'] as $moduleName => $module) {
				if (isset($module['deeplink'])) {
					$this->doMatch($groupName, $moduleName, $module, $currentDeeplink, $currentPriority);
				}
				if (preg_match('#(^active | active | active$)#', $module['class'])) {
					$active = array($groupName, $moduleName);
				}
			}
		}

		if ($currentDeeplink) {
			if ($active) {
				list($groupName, $moduleName) = $active;
				$classes = explode(' ', $navigation[$groupName]['modules'][$moduleName]['class']);
				$classes = array_map('trim', $classes);
				$pos     = array_search('active', $classes);
				unset($classes[$pos]);
				$navigation[$groupName]['modules'][$moduleName]['class'] = implode(' ', $classes);
			}

			list($groupName, $moduleName) = $currentDeeplink;
			$navigation[$groupName]['modules'][$moduleName]['class'] .= ' active';
		}

		return $navigation;
	}

	/**
	 * Check if the current menu item match the current request.
	 *
	 * @param string $groupName
	 * @param string $moduleName
	 * @param array  $module
	 * @param array  $currentDeeplink
	 * @param int    $currentPriority
	 *
	 * @return bool
	 *
	 * @SuppressWarnings(PHPMD.NPathComplexity)
	 */
	protected function doMatch(
		$groupName,
		$moduleName,
		array $module,
		&$currentDeeplink,
		&$currentPriority
	) {
		$input = \Input::getInstance();

		$search = $this->parseSearchParametersFromModule($module);

		$priority = isset($module['priority']) ? (int) $module['priority'] : 10;

		if ($priority <= $currentPriority) {
			return false;
		}

		$match = true;
		foreach ($search as $parameter => $value) {
			if ($input->get($parameter) != $value) {
				$match = false;
				break;
			}
		}

		if (!$match && $module['deepsearch'] !== false && isset($search['id'])) {
			$match = $this->doDeepSearch($module, $search);
		}

		if ($match) {
			$currentDeeplink = array($groupName, $moduleName);
			$currentPriority = $priority;
		}
	}

	/**
	 * Parse the search parameters from the module item.
	 *
	 * @param array $module
	 *
	 * @return array
	 */
	protected function parseSearchParametersFromModule(array $module)
	{
		if (isset($module['search'])) {
			if (!is_array($module['search'])) {
				parse_str($module['search'], $search);
			}
			else {
				$search = $module['search'];
			}
		}
		else if (!is_array($module['deeplink'])) {
			parse_str($module['deeplink'], $search);
		}
		else {
			$search = $module['deeplink'];
		}

		return $search;
	}

	/**
	 * @param array $module
	 *
	 * @return bool
	 *
	 * @SuppressWarnings(PHPMD.Superglobals)
	 * @SuppressWarnings(PHPMD.CamelCaseVariableName)
	 */
	protected function doDeepSearch(array $module, $search)
	{
		$input = \Input::getInstance();

		$rootModule = $module;

		if (isset($search['do'])) {
			foreach ($GLOBALS['BE_MOD'] as $group) {
				if (isset($group[$search['do']])) {
					$rootModule = $group[$search['do']];
					break;
				}
			}
		}

		$searchTable = $search['table']
			? $search['table']
			: $rootModule['tables'][0];

		$searchId = $search['id'];

		$currentTable = $input->get('table')
			? $input->get('table')
			: $rootModule['tables'][0];

		$currentId = $input->get('id');

		return $this->doRecordMatch($searchTable, $searchId, $currentTable, $currentId);
	}

	/**
	 * Match menu item by database record.
	 *
	 * @param string $searchTable
	 * @param string $searchId
	 * @param string $currentTable
	 * @param string $currentId
	 *
	 * @return bool
	 *
	 * @SuppressWarnings(PHPMD.Superglobals)
	 * @SuppressWarnings(PHPMD.CamelCaseVariableName)
	 */
	protected function doRecordMatch($searchTable, $searchId, $currentTable, $currentId)
	{
		if ($searchTable == $currentTable) {
			return $searchId == $currentId;
		}
		else {
			$this->loadDataContainer($currentTable);

			if (
				isset($GLOBALS['TL_DCA'][$currentTable]) &&
				isset($GLOBALS['TL_DCA'][$currentTable]['config']['dataContainer'])
			) {
				switch ($GLOBALS['TL_DCA'][$currentTable]['config']['dataContainer']) {
					case 'Table':
						return $this->doRecordMatchOnDcTable(
							$searchTable,
							$searchId,
							$currentTable,
							$currentId
						);

					default:
				}
			}
		}

		return false;
	}

	/**
	 * Match menu item by database record based on DC_Table relations.
	 *
	 * @param string $searchTable
	 * @param string $searchId
	 * @param string $currentTable
	 * @param string $currentId
	 *
	 * @return bool
	 *
	 * @SuppressWarnings(PHPMD.Superglobals)
	 * @SuppressWarnings(PHPMD.CamelCaseVariableName)
	 */
	protected function doRecordMatchOnDcTable($searchTable, $searchId, $currentTable, $currentId)
	{
		if (isset($GLOBALS['TL_DCA'][$currentTable]['config']['ptable'])) {
			$parentTable = $GLOBALS['TL_DCA'][$currentTable]['config']['ptable'];

			// Edit mode: $id is the id of the table record
			if (\Input::getInstance()
				->get('act')
			) {
				$currentTable = preg_replace('~[^\w\d]~', '', $currentTable);

				$resultSet = \Database::getInstance()
					->prepare('SELECT * FROM ' . $currentTable . ' WHERE id=?')
					->execute($currentId);

				if ($resultSet->next()) {
					$row = $resultSet->row();

					if (isset($row['pid'])) {
						return $this->doRecordMatch($searchTable, $searchId, $parentTable, $row['pid']);
					}
				}
			}

			// Child listing mode: $id is the id of the parent table record
			else {
				return $this->doRecordMatch($searchTable, $searchId, $parentTable, $currentId);
			}
		}

		return false;
	}

	/**
	 * Redirect to the deeplink target url.
	 *
	 * @return void
	 * @throws \RuntimeException
	 *
	 * @SuppressWarnings(PHPMD.Superglobals)
	 * @SuppressWarnings(PHPMD.CamelCaseVariableName)
	 */
	public function generate()
	{
		$activeModuleName = \Input::getInstance()
			->get('do');
		foreach ($GLOBALS['BE_MOD'] as $group) {
			foreach ($group as $moduleName => $module) {
				if ($moduleName == $activeModuleName && isset($module['deeplink'])) {
					if (is_array($module['deeplink'])) {
						$query = http_build_query($module['deeplink']);
					}
					else {
						$query = $module['deeplink'];
					}

					$this->redirect('contao/main.php?' . $query);
				}
			}
		}

		throw new \RuntimeException(
			'Could not find the deeplink target, ' .
			'you need to specify the "deeplink" property for this module!'
		);
	}

	/**
	 * Compile the current element
	 */
	protected function compile()
	{
	}
}
