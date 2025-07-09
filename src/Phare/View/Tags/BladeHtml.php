<?php

namespace Phare\View\Tags;

use Phare\View\BladeOneHtml;

trait BladeHtml
{
    use BladeOneHtml;

    public function useTailwind($useCDN = false): void
    {
        $this->style = 'tailwind';
        $tw = [
            'button' => 'btn',
            'input' => 'form-control',
            'textarea' => 'form-control',
            'checkbox_item' => 'form-check-input',
            'select' => 'form-control',
            'file' => 'form-control',
            'range' => 'form-range',
            'radio' => 'form-check-input',
            'radio_item' => 'form-check-input',
            'ul' => 'list-group',
            'ul_item' => 'list-group-item',
            'ol' => 'list-group',
            'ol_item' => 'list-group-item',
            'table' => 'table',
            'alert' => 'alert',
            'container' => 'container-fluid',
            'row' => 'row',
            'col' => 'col',
        ];
        $this->defaultClass = array_merge($this->defaultClass, $tw);
        $this->pattern['checkbox'] = '<!--suppress XmlInvalidId -->
<div class="form-check">
            <input type="checkbox" class="form-check-input" {{inner}}>
            <label class="form-check-label" for={{id}} >{{between}}</label>
            </div>{{post}}';
        $this->pattern['radio'] = '<!--suppress XmlInvalidId -->
<div class="form-check">
            <input type="radio" class="form-check-input" {{inner}}>
            <label class="form-check-label" for={{id}} >{{between}}</label>
            </div>{{post}}';

        $this->pattern['checkboxes_item'] = $this->pattern['checkbox'];
        $this->pattern['radios_item'] = $this->pattern['radio'];

        if ($useCDN) {
            // $this->addCss('', 'tailwind');
            $this->addJs('<script src="https://cdn.tailwindcss.com"></script>', 'tailwind');
        }
    }

    public function useDaisyui($useCDN = false): void
    {
        $this->style = 'tailwind-daisyui';
        $ds = [
            'button' => 'btn',
            'input' => 'form-control',
            'textarea' => 'form-control',
            'checkbox_item' => 'form-check-input',
            'select' => 'form-control',
            'file' => 'form-control',
            'range' => 'form-range',
            'radio' => 'form-check-input',
            'radio_item' => 'form-check-input',
            'ul' => 'list-group',
            'ul_item' => 'list-group-item',
            'ol' => 'list-group',
            'ol_item' => 'list-group-item',
            'table' => 'table',
            'alert' => 'alert',
            'container' => 'container-fluid',
            'row' => 'row',
            'col' => 'col',
        ];
        $this->defaultClass = array_merge($ds, $this->defaultClass);
        $this->pattern['checkbox'] = '<!--suppress XmlInvalidId -->
<div class="form-check">
            <input type="checkbox" class="form-check-input" {{inner}}>
            <label class="form-check-label" for={{id}} >{{between}}</label>
            </div>{{post}}';
        $this->pattern['radio'] = '<!--suppress XmlInvalidId -->
<div class="form-check">
            <input type="radio" class="form-check-input" {{inner}}>
            <label class="form-check-label" for={{id}} >{{between}}</label>
            </div>{{post}}';

        $this->pattern['checkboxes_item'] = $this->pattern['checkbox'];
        $this->pattern['radios_item'] = $this->pattern['radio'];

        if ($useCDN) {
            $this->addJs('https://cdn.tailwindcss.com?plugins=typography', 'tailwind');
            $this->addCss('https://cdn.jsdelivr.net/npm/daisyui@4.5.0/dist/full.min.css', 'daisyui');
        }
    }
}
