<?php

namespace mythdigital\bundles\web\assets\bundles;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;
use craft\web\View;
use yii\web\JqueryAsset;

/**
 * Asset bundle for the Control Panel
 *
 * @author Myth Digital
 * @since 1.1
 */
class BundleAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = __DIR__ . '/src';

        $this->depends = [
            CpAsset::class,
            JqueryAsset::class,
        ];

        $this->js[] = 'js/bundles.js';

        parent::init();
    }
}
