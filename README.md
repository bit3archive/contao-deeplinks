# Deeplinks

[![Build Status](https://travis-ci.org/bit3/contao-deeplinks.png?branch=master)](https://travis-ci.org/bit3/contao-deeplinks) [![mess checked](https://bit3.de/files/Icons/mess-checked.png)](https://github.com/bit3/php-coding-standard) [![style checked](https://bit3.de/files/Icons/style-checked.png)](https://github.com/bit3/php-coding-standard)

Contao does not support backend menu items that are deeplinks to special functions or deep links to specific records.
The deeplinks extension make it possible to define menu items, that work as deep links and also highlighted as active,
if the link is opened.

## Define deep links

You define deep links in the global `$GLOBALS['BE_MOD']` array, like normal menu items.

```php
$GLOBALS['BE_MOD']['my_module']['my_deeplink'] = array(
	'icon'       => 'system/modules/my_module/assets/images/my_deeplink.png',
	'deeplink'   => 'do=my_menu&id=1',
	'search'     => 'do=my_menu&table=tl_my_table&id=1',
	'deepsearch' => true,
	'priority'   => 10,
);

$GLOBALS['BE_MOD']['my_module']['my_menu'] = array(
	'tables'     => array('tl_my_table', 'tl_my_sub_table'),
	'icon'       => 'system/modules/my_module/assets/images/my_menu.png',
);
```

As you can see in the `my_deeplink` menu item, the `deeplink` property define the query string for the deep link.
If you click on the menu item, you will be redirected to `/contao/main.php?<?= $GLOBALS['BE_MOD']['my_module']['my_deeplink']['deeplink']; ?>`.


The `search` property is required to find the correct deep link in the menu.
If no `search` property is defined, the `deeplink` property will be used.
The parameters from `search` will be matched against the GET-Parameters.
If all parameters match, the deep link is supposed to be active.

Hint: The `table` parameter is special here, if no `$_GET['table']` is defined,
the algorithm search the menu item that match the `$_GET['do']` parameter
(in the example above, it will be `$GLOBALS['BE_MOD']['my_module']['my_menu']`)
and use the first table (`tl_my_table`) of it in replacement of the `$_GET['table']` parameter.

Hopefully you can see now, why there is a difference between `deeplink` and `search`.
`search` is usually only needed, if `deeplink` does not contain a `table` parameter.


The `deepsearch` property tell the algorithm to search from child tables up to the top tables. That means, if the table `tl_my_sub_table` is a child table of `tl_my_table`,
the algorithm search through the records in the parent-child tables, until the table defined in `search` is found or there is no more parent table defined.
The deep search require a correct `ptable`/`ctable` definition in the DCA.
Currently `DC_Table` is the only supported DataContainer type.

By default `deepsearch` is **enabled**!


The `priority` property give you control over the matching priority, if you have more deeplinks that compete.
By default the algorithm will go up-down through the menu items and break on the first menu item, that match.
But if you have two compete items, a "deeper" item that is below the less-deep item, the less-deep item may shown as active,
even if the deeper item match "better".

```php
$GLOBALS['BE_MOD']['my_module']['my_deeplink'] = array(
	'icon'       => 'system/modules/my_module/assets/images/my_deeplink.png',
	'deeplink'   => 'do=my_menu&id=1',
	'search'     => 'do=my_menu&table=tl_my_table&id=1',
	'priority'   => 10,
);
$GLOBALS['BE_MOD']['my_module']['my_deeplink_edit'] = array(
	'icon'       => 'system/modules/my_module/assets/images/my_deeplink.png',
	'deeplink'   => 'do=my_menu&act=edit&id=1',
	'search'     => 'do=my_menu&act=edit&table=tl_my_table&id=1',
	'priority'   => 11,
);
```

In this example `my_deeplink` and `my_deeplink_edit` will match on the url `main.php?do=my_menu&id=1&act=edit`.
But the priority of `my_deeplink_edit` is higher, so even if `my_deeplink` match the search and is the first matching item,
`my_deeplink_edit` will be supposed to be active.

By default `priority` is set to **10**!

## Dynamic deep links

Deep links make more sense, if you create them dynamically.
For this you can use the `deeplinks-create` event, that is dispatched in an early system initialisation state.

To listen on the event, put the following into your `config.php`:
```php
$GLOBALS['TL_EVENTS']['deeplinks-create'][] = array('MyClass', 'eventShortcutsCreate');
```

Then create a class `MyClass`:
```php
class MyClass
{
	static public function eventShortcutsCreate()
	{
		$database = \Database::getInstance();

		// fetch items from database and create
		// the items in $GLOBALS['BE_MOD'] dynamically
	}
}
```

Hint: It is not necessary to use the `deeplinks-create`. You can define the items in `$GLOBALS['BE_MOD']` everywhere and everytime you want.
But if you create your items **after** the `deeplinks-create` event, you need to define the callback by yourself!

```php
$GLOBALS['BE_MOD']['my_module']['my_deeplink'] = array(
	...
	'callback' => 'Bit3\Contao\Deeplinks\Deeplinks',
);
```

For all items defined before the `deeplinks-create` event, the callback will be added dynamically.
