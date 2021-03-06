<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

class statslive extends Module
{
    private $html = '';

    public function __construct()
    {
        $this->name = 'statslive';
        $this->tab = 'analytics_stats';
        $this->version = '2.1.1';
        $this->author = 'PrestaShop';
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->trans('Visitors online', [], 'Modules.Statslive.Admin');
        $this->description = $this->trans('Enrich your stats, add a list of the visitors who are currently browsing your store.', [], 'Modules.Statslive.Admin');
        $this->ps_versions_compliancy = ['min' => '1.7.1.0', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        return parent::install() && $this->registerHook('AdminStatsModules');
    }

    /**
     * Get the number of online customers
     *
     * @return array(array, int) array of online customers entries, number of online customers
     */
    private function getCustomersOnline()
    {
        if ($maintenance_ips = Configuration::get('PS_MAINTENANCE_IP')) {
            $maintenance_ips = implode(',', array_filter(array_map('ip2long', array_map('trim', explode(',', $maintenance_ips)))));
        }

        if (Configuration::get('PS_STATSDATA_CUSTOMER_PAGESVIEWS')) {
            $sql = 'SELECT u.id_customer, u.firstname, u.lastname, pt.name as page
					FROM `' . _DB_PREFIX_ . 'connections` c
					LEFT JOIN `' . _DB_PREFIX_ . 'connections_page` cp ON c.id_connections = cp.id_connections
					LEFT JOIN `' . _DB_PREFIX_ . 'page` p ON p.id_page = cp.id_page
					LEFT JOIN `' . _DB_PREFIX_ . 'page_type` pt ON p.id_page_type = pt.id_page_type
					INNER JOIN `' . _DB_PREFIX_ . 'guest` g ON c.id_guest = g.id_guest
					INNER JOIN `' . _DB_PREFIX_ . 'customer` u ON u.id_customer = g.id_customer
					WHERE cp.`time_end` IS NULL
						' . Shop::addSqlRestriction(false, 'c') . '
						AND cp.`time_start` > NOW() - INTERVAL 15 MINUTE
					' . ($maintenance_ips ? 'AND c.ip_address NOT IN (' . preg_replace('/[^,0-9]/', '', $maintenance_ips) . ')' : '') . '
					GROUP BY u.id_customer
					ORDER BY u.firstname, u.lastname';
        } else {
            $sql = 'SELECT u.id_customer, u.firstname, u.lastname, "-" as page
					FROM `' . _DB_PREFIX_ . 'connections` c
					INNER JOIN `' . _DB_PREFIX_ . 'guest` g ON c.id_guest = g.id_guest
					INNER JOIN `' . _DB_PREFIX_ . 'customer` u ON u.id_customer = g.id_customer
					WHERE c.`date_add` > NOW() - INTERVAL 15 MINUTE
						' . Shop::addSqlRestriction(false, 'c') . '
					' . ($maintenance_ips ? 'AND c.ip_address NOT IN (' . preg_replace('/[^,0-9]/', '', $maintenance_ips) . ')' : '') . '
					GROUP BY u.id_customer
					ORDER BY u.firstname, u.lastname';
        }
        $results = Db::getInstance((bool) _PS_USE_SQL_SLAVE_)->executeS($sql);

        return [$results, Db::getInstance()->NumRows()];
    }

    /**
     * Get the number of online visitors
     *
     * @return array(array, int) array of online visitors entries, number of online visitors
     */
    private function getVisitorsOnline()
    {
        if ($maintenance_ips = Configuration::get('PS_MAINTENANCE_IP')) {
            $maintenance_ips = implode(',', array_filter(array_map('ip2long', array_map('trim', explode(',', $maintenance_ips)))));
        }

        if (Configuration::get('PS_STATSDATA_CUSTOMER_PAGESVIEWS')) {
            $sql = 'SELECT c.id_guest, c.ip_address, c.date_add, c.http_referer, pt.name as page
					FROM `' . _DB_PREFIX_ . 'connections` c
					LEFT JOIN `' . _DB_PREFIX_ . 'connections_page` cp ON c.id_connections = cp.id_connections
					LEFT JOIN `' . _DB_PREFIX_ . 'page` p ON p.id_page = cp.id_page
					LEFT JOIN `' . _DB_PREFIX_ . 'page_type` pt ON p.id_page_type = pt.id_page_type
					INNER JOIN `' . _DB_PREFIX_ . 'guest` g ON c.id_guest = g.id_guest
					WHERE (g.id_customer IS NULL OR g.id_customer = 0)
						' . Shop::addSqlRestriction(false, 'c') . '
						AND cp.`time_end` IS NULL
					AND cp.`time_start` > NOW() - INTERVAL 15 MINUTE
					' . ($maintenance_ips ? 'AND c.ip_address NOT IN (' . preg_replace('/[^,0-9]/', '', $maintenance_ips) . ')' : '') . '
					GROUP BY c.id_connections
					ORDER BY c.date_add DESC';
        } else {
            $sql = 'SELECT c.id_guest, c.ip_address, c.date_add, c.http_referer, "-" as page
					FROM `' . _DB_PREFIX_ . 'connections` c
					INNER JOIN `' . _DB_PREFIX_ . 'guest` g ON c.id_guest = g.id_guest
					WHERE (g.id_customer IS NULL OR g.id_customer = 0)
						' . Shop::addSqlRestriction(false, 'c') . '
						AND c.`date_add` > NOW() - INTERVAL 15 MINUTE
					' . ($maintenance_ips ? 'AND c.ip_address NOT IN (' . preg_replace('/[^,0-9]/', '', $maintenance_ips) . ')' : '') . '
					ORDER BY c.date_add DESC';
        }

        $results = Db::getInstance((bool) _PS_USE_SQL_SLAVE_)->executeS($sql);

        return [$results, Db::getInstance()->NumRows()];
    }

    public function hookAdminStatsModules($params)
    {
        list($customers, $total_customers) = $this->getCustomersOnline();
        list($visitors, $total_visitors) = $this->getVisitorsOnline();
        $irow = 0;

        $this->html .= '<script type="text/javascript">
			$("#calendar").remove();
		</script>';
        if (!Configuration::get('PS_STATSDATA_CUSTOMER_PAGESVIEWS')) {
            $this->html .= '
				<div class="alert alert-info">' .
                $this->trans('You must activate the "Save page views for each customer" option in the "Data mining for statistics" (StatsData) module in order to see the pages that your visitors are currently viewing.', [], 'Modules.Statslive.Admin') . '
				</div>';
        }
        $this->html .= '
			<h4> ' . $this->trans('Current online customers', [], 'Modules.Statslive.Admin') . '</h4>';
        if ($total_customers) {
            $this->html .= $this->trans('Total:', [], 'Modules.Statslive.Admin') . ' ' . (int) $total_customers . '
			<table class="table">
				<thead>
					<tr>
						<th class="center"><span class="title_box active">' . $this->trans('Customer ID', [], 'Admin.Advparameters.Feature') . '</span></th>
						<th class="center"><span class="title_box active">' . $this->trans('Name', [], 'Admin.Global') . '</span></th>
						<th class="center"><span class="title_box active">' . $this->trans('Current page', [], 'Modules.Statslive.Admin') . '</span></th>
						<th class="center"><span class="title_box active">' . $this->trans('View customer profile', [], 'Modules.Statslive.Admin') . '</span></th>
					</tr>
				</thead>
				<tbody>';
            foreach ($customers as $customer) {
                $this->html .= '
					<tr' . ($irow++ % 2 ? ' class="alt_row"' : '') . '>
						<td class="center">' . $customer['id_customer'] . '</td>
						<td class="center">' . $customer['firstname'] . ' ' . $customer['lastname'] . '</td>
						<td class="center">' . $customer['page'] . '</td>
						<td class="center">
							<a href="' . Tools::safeOutput('index.php?tab=AdminCustomers&id_customer=' . $customer['id_customer'] . '&viewcustomer&token=' . Tools::getAdminToken('AdminCustomers' . (int) Tab::getIdFromClassName('AdminCustomers') . (int) $this->context->employee->id)) . '"
								target="_blank">
								<img src="../modules/' . $this->name . '/logo.gif" />
							</a>
						</td>
					</tr>';
            }
            $this->html .= '
				</tbody>
			</table>';
        } else {
            $this->html .= '<p class="alert alert-warning">' . $this->trans('There are no active customers online right now.', [], 'Modules.Statslive.Admin') . '</p>';
        }
        $this->html .= '
			<h4> ' . $this->trans('Current online visitors', [], 'Modules.Statslive.Admin') . '</h4>';
        if ($total_visitors) {
            $this->html .= $this->trans('Total:', [], 'Modules.Statslive.Admin') . ' ' . (int) $total_visitors . '
			<div>
				<table class="table">
					<thead>
						<tr>
							<th class="center"><span class="title_box active">' . $this->trans('Guest ID', [], 'Modules.Statslive.Admin') . '</span></th>
							<th class="center"><span class="title_box active">' . $this->trans('IP', [], 'Modules.Statslive.Admin') . '</span></th>
							<th class="center"><span class="title_box active">' . $this->trans('Last activity', [], 'Modules.Statslive.Admin') . '</span></th>
							<th class="center"><span class="title_box active">' . $this->trans('Current page', [], 'Modules.Statslive.Admin') . '</span></th>
							<th class="center"><span class="title_box active">' . $this->trans('Referrer', [], 'Admin.Shopparameters.Feature') . '</span></th>
						</tr>
					</thead>
					<tbody>';
            foreach ($visitors as $visitor) {
                $this->html .= '<tr' . ($irow++ % 2 ? ' class="alt_row"' : '') . '>
						<td class="center">' . $visitor['id_guest'] . '</td>
						<td class="center">' . long2ip($visitor['ip_address']) . '</td>
						<td class="center">' . Tools::substr($visitor['date_add'], 11) . '</td>
						<td class="center">' . (isset($visitor['page']) ? $visitor['page'] : $this->trans('Undefined', [], 'Admin.Shopparameters.Feature')) . '</td>
						<td class="center">' . (empty($visitor['http_referer']) ? $this->trans('None', [], 'Admin.Global') : parse_url($visitor['http_referer'], PHP_URL_HOST)) . '</td>
					</tr>';
            }
            $this->html .= '
					</tbody>
				</table>
			</div>';
        } else {
            $this->html .= '<p class="alert alert-warning">' . $this->trans('There are no visitors online.', [], 'Admin.Shopparameters.Feature') . '</p>';
        }
        $this->html .= '
			<h4>' . $this->trans('Notice', [], 'Modules.Statslive.Admin') . '</h4>
			<p class="alert alert-info">' . $this->trans('Maintenance IPs are excluded from the online visitors.', [], 'Modules.Statslive.Admin') . '</p>
			<a class="btn btn-default" href="' . Tools::safeOutput('index.php?controller=AdminMaintenance&token=' . Tools::getAdminTokenLite('AdminMaintenance')) . '">
				<i class="icon-share-alt"></i> ' . $this->trans('Add or remove an IP address.', [], 'Modules.Statslive.Admin') . '
			</a>
		';

        return $this->html;
    }
}
