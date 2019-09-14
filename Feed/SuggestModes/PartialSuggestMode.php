<?php

namespace Statamic\Addons\Feed\SuggestModes;

use Statamic\API\Folder;
use Statamic\Addons\Suggest\Modes\AbstractMode;

class PartialSuggestMode extends AbstractMode
{
    public function suggestions()
    {
        return collect(Folder::disk('theme')->getFiles('partials', true))
            ->map(function ($path, $key) {
                $value = modify($path)->removeLeft('partials/')->removeRight('.html')->fetch();

                return ['value' => $value, 'text' => $value];
            })->values()
            ->all();
    }
}
