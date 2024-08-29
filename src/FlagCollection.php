<?php

namespace Fullbay\FeatureFlags;

use Gamez\Illuminate\Support\TypedCollection;

/** @extends TypedCollection<string, Flag> */
class FlagCollection extends TypedCollection
{
    protected static $allowedTypes = [Flag::class];
}
