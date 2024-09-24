<?php

namespace upsell\airbrake;

use Closure;
use yii\base\BaseObject;

class ErrorsFilter extends BaseObject
{

    /**
     * Array of errors to ignore
     * @var array
     */
    public $errors = [];


   
    private $errorsFilter;

    public function init()
    {
        parent::init();

        $this->errorsFilter = function ($notice) {

                if ( in_array($notice['errors'][0]['type'],  $this->errors)) {
                 return null;
                }

            return $notice;
        };
    }

    /**
     * Returns a callable that replaces occurrences of $params values
     * with value specified in $replacement.
     * @return callable|Closure Closure to set as filter
     */
    public function getParamsFilter()
    {
        return $this->errorsFilter;
    }

}
