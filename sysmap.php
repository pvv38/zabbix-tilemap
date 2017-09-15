<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/maps.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Configuration of network maps');
$page['file'] = 'sysmap.php';
$page['scripts'] = ['class.svg.canvas.js', 'class.svg.map.js', 'class.cmap.js', 'class.cviewswitcher.js',
	'multiselect.js'
];
$page['type'] = detect_page_type();

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'sysmapid' =>	[T_ZBX_INT, O_MAND, P_SYS,	DB_ID,		null],
	'selementid' =>	[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'sysmap' =>		[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({action})'],
	'selements' =>	[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'links' =>		[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	// actions
	'action' =>		[T_ZBX_STR, O_OPT, P_ACT,	IN('"update"'),	null],
	'delete' =>		[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'cancel' =>		[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form' =>		[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form_refresh' => [T_ZBX_INT, O_OPT, null,	null,		null],
	// ajax
	'favobj' =>		[T_ZBX_STR, O_OPT, P_ACT,	null,		null],
	'favid' =>		[T_ZBX_STR, O_OPT, P_ACT,	null,		null]
];
check_fields($fields);

/*
 * Ajax
 */
if (isset($_REQUEST['favobj'])) {
	$json = new CJson();

	if (getRequest('favobj') == 'sysmap' && hasRequest('action') && getRequest('action') == 'update') {
		$sysmapid = getRequest('sysmapid', 0);

		@ob_start();

		try {
			DBstart();

			$sysmap = API::Map()->get([
				'sysmapids' => $sysmapid,
				'editable' => true,
				'output' => ['sysmapid']
			]);
			$sysmap = reset($sysmap);

			if ($sysmap === false) {
				throw new Exception(_('Access denied!'));
			}

			$sysmapUpdate = $json->decode($_REQUEST['sysmap'], true);
			$sysmapUpdate['sysmapid'] = $sysmapid;
			$sysmapUpdate['lines'] = [];

			if (array_key_exists('shapes', $sysmapUpdate)) {
				foreach ($sysmapUpdate['shapes'] as $key => &$shape) {
					if (array_key_exists('sysmap_shapeid', $shape) && !is_numeric($shape['sysmap_shapeid'])) {
						unset($shape['sysmap_shapeid']);
					}

					if ($shape['type'] == SYSMAP_SHAPE_TYPE_LINE) {
						$sysmapUpdate['lines'][$key] = CMapHelper::convertShapeToLine($shape);
						unset($sysmapUpdate['shapes'][$key]);
					}
				}
				unset($shape);
			}

			$result = API::Map()->update($sysmapUpdate);

			if ($result !== false) {
				echo 'if (confirm('.CJs::encodeJson(_('Map is updated! Return?')).')) { location.href = "sysmaps.php"; }';
			}
			else {
				throw new Exception(_('Map update failed.'));
			}

			DBend(true);
		}
		catch (Exception $e) {
			DBend(false);
			$msg = [$e->getMessage()];

			foreach (clear_messages() as $errMsg) {
				$msg[] = $errMsg['type'].': '.$errMsg['message'];
			}

			ob_clean();

			echo 'alert('.zbx_jsvalue(implode("\r\n", $msg)).');';
		}

		@ob_flush();
		exit;

	}
}

if (PAGE_TYPE_HTML != $page['type']) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

/*
 * Permissions
 */
if (isset($_REQUEST['sysmapid'])) {
	$sysmap = API::Map()->get([
		'output' => ['sysmapid', 'expand_macros', 'grid_show', 'grid_align', 'grid_size', 'width', 'height',
			'iconmapid', 'backgroundid', 'label_location'
		],
		'selectShapes' => ['sysmap_shapeid', 'type', 'x', 'y', 'width', 'height', 'text', 'font', 'font_size',
			'font_color', 'text_halign', 'text_valign', 'border_type', 'border_width', 'border_color',
			'background_color', 'zindex'
		],
		'selectLines' => ['sysmap_shapeid', 'x1', 'y1', 'x2', 'y2', 'line_type', 'line_width', 'line_color', 'zindex'],
		'selectSelements' => API_OUTPUT_EXTEND,
		'selectLinks' => API_OUTPUT_EXTEND,
		'sysmapids' => getRequest('sysmapid'),
		'editable' => true,
		'preservekeys' => true
	]);
	if (!$sysmap) {
		access_deny();
	}
	else {
		$sysmap = reset($sysmap);
	}
}

/*
 * Display
 */
$data = [
	'sysmap' => $sysmap,
	'iconList' => [],
	'defaultAutoIconId' => null,
	'defaultIconId' => null,
	'defaultIconName' => null
];

// get selements
add_elementNames($data['sysmap']['selements']);

foreach ($data['sysmap']['lines'] as $line) {
	$data['sysmap']['shapes'][] = CMapHelper::convertLineToShape($line);
}
unset($data['sysmap']['lines']);

$data['sysmap']['selements'] = zbx_toHash($data['sysmap']['selements'], 'selementid');
$data['sysmap']['shapes'] = zbx_toHash($data['sysmap']['shapes'], 'sysmap_shapeid');
$data['sysmap']['links'] = zbx_toHash($data['sysmap']['links'], 'linkid');

// get links
foreach ($data['sysmap']['links'] as &$link) {
	foreach ($link['linktriggers'] as $lnum => $linkTrigger) {
		$dbTrigger = API::Trigger()->get([
			'triggerids' => $linkTrigger['triggerid'],
			'output' => ['description', 'expression'],
			'selectHosts' => API_OUTPUT_EXTEND,
			'preservekeys' => true,
			'expandDescription' => true
		]);
		$dbTrigger = reset($dbTrigger);
		$host = reset($dbTrigger['hosts']);

		$link['linktriggers'][$lnum]['desc_exp'] = $host['name'].NAME_DELIMITER.$dbTrigger['description'];
	}
	order_result($link['linktriggers'], 'desc_exp');
}
unset($link);

// get iconmapping
if ($data['sysmap']['iconmapid']) {
	$iconMap = API::IconMap()->get([
		'iconmapids' => $data['sysmap']['iconmapid'],
		'output' => ['default_iconid'],
		'preservekeys' => true
	]);
	$iconMap = reset($iconMap);
	$data['defaultAutoIconId'] = $iconMap['default_iconid'];
}

$images = API::Image()->get([
	'output' => ['imageid', 'name'],
	'filter' => ['imagetype' => IMAGE_TYPE_ICON],
	'select_image' => true
]);
foreach ($images as $image) {
	$image['image'] = base64_decode($image['image']);
	$ico = imagecreatefromstring($image['image']);

	$data['iconList'][] = [
		'imageid' => $image['imageid'],
		'name' => $image['name'],
		'width' => imagesx($ico),
		'height' => imagesy($ico)
	];

	if ($image['name'] == MAP_DEFAULT_ICON || !isset($data['defaultIconId'])) {
		$data['defaultIconId'] = $image['imageid'];
		$data['defaultIconName'] = $image['name'];
	}
}
if ($data['iconList']) {
	CArrayHelper::sort($data['iconList'], ['name']);
	$data['iconList'] = array_values($data['iconList']);
}

$data['theme'] = getUserGraphTheme();

// render view
$sysmapView = new CView('monitoring.sysmap.constructor', $data);
$sysmapView->render();
$sysmapView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
