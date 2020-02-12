<?php
/**
 * Bundles plugin for Craft CMS 3.x
 *
 * Craft Commerce Bundles Plugin
 *
 * @link      https://myth.digital/
 * @copyright Copyright (c) 2020 Myth Digital
 */

namespace mythdigital\bundles\services;

use mythdigital\bundles\Bundles;

use Craft;
use craft\base\Component;

/**
 * @author    Myth Digital
 * @package   Bundles
 * @since     1.0.0
 */
class BundleService extends Component
{
    // Public Methods
    // =========================================================================

    /*
     * @return mixed
     */
    public function exampleService()
    {
        $result = 'something';
        // Check our Plugin's settings for `someAttribute`
        if (Bundles::$plugin->getSettings()->someAttribute) {
        }

        return $result;
    }
}
