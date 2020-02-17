<?php

namespace mythdigital\bundles\events;

use mythdigital\bundles\models\Bundle;
use yii\base\Event;

/**
 * Class BundleEvent
 *
 * @author Myth Digital
 * @since 1.0.0
 */
class BundleEvent extends Event
{
    // Properties
    // =========================================================================

    /**
     * @var Bundle The discount model
     */
    public $bundle;

    /**
     * @var bool If this is a new discount
     */
    public $isNew;
}
