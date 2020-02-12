<?php
/**
 * Bundles plugin for Craft CMS 3.x
 *
 * Craft Commerce Bundles Plugin
 *
 * @link      https://myth.digital/
 * @copyright Copyright (c) 2020 Myth Digital
 */

namespace mythdigital\bundles\assetbundles\Bundles;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * @author    Myth Digital
 * @package   Bundles
 * @since     1.0.0
 */
class BundlesAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = "@mythdigital/bundles/assetbundles/bundles/dist";

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/Bundles.js',
        ];

        $this->css = [
            'css/Bundles.css',
        ];

        parent::init();
    }
}
