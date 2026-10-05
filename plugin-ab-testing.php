<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (c) 2026 Aura Logic
/**
 * Plugin Name: Aura Logic A/B Testing
 * Description: Experimentos entre páginas y medición de conversiones, independientes del constructor.
 * Version: 0.3.3
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Author: Aura Logic
 * Author URI: https://auralogic.dev/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: builder-ab-testing
 */
if (!defined('ABSPATH')) { exit; }
define('BAT_VERSION', '0.3.3');
define('BAT_FILE', __FILE__);
require_once __DIR__ . '/includes/class-bat-plugin.php';
require_once __DIR__ . '/includes/class-bat-admin.php';
require_once __DIR__ . '/includes/class-bat-rest.php';
require_once __DIR__ . '/includes/class-bat-integrations.php';
register_activation_hook(__FILE__, ['BAT_Plugin', 'install']);
add_action('plugins_loaded', ['BAT_Plugin', 'boot']);
