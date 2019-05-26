<?php namespace Statamic\Addons\PrestigeWorldWide;

use Statamic\API\Form;
use Statamic\Addons\Suggest\Modes\AbstractMode;

class PrestigeWorldWideSuggestMode extends AbstractMode
{
    public function suggestions()
    {
        $forms = Form::all();
        $formvalues = [];
        $formvalues[] = ['value' => null, 'text' => null];

        foreach ($forms as $form) {
            $formvalues[] = [
                'value' => $form['name'],
                'text' => $form['title']
            ];
        }
        return $formvalues;
    }
}
