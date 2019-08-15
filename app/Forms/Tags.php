<?php

namespace Statamic\Forms;

use Statamic\API\URL;
use Statamic\API\Form;
use DebugBar\DebugBarException;
use Statamic\Tags\Tags as BaseTags;
use Illuminate\Support\Facades\Crypt;
use DebugBar\DataCollector\ConfigCollector;

class Tags extends BaseTags
{
    protected static $handle = 'form';

    /**
     * @var string
     */
    private $formHandle;

    /**
     * @var object
     */
    private $errorBag;

    /**
     * {{ form:* }} ... {{ /form:* }}
     */
    public function __call($method, $args)
    {
        $this->parameters['form'] = $this->method;

        return $this->create();
    }

    /**
     * Maps to {{ form:set }}
     *
     * Allows you to inject the formset into the context so child tags can use it.
     *
     * @return string
     */
    public function set()
    {
        $this->context['form'] = $this->getParam(['in', 'is', 'form', 'formset']);

        return $this->parse($this->context);
    }

    /**
     * Maps to {{ form:create }}
     *
     * @return string
     */
    public function create()
    {
        $data = [];

        $this->formHandle = $this->getForm();
        $this->errorBag = $this->getErrorBag();

        $html = $this->formOpen(route('statamic.forms.store'));

        if ($this->hasErrors()) {
            $data['error']  = $this->getErrors();
            $data['errors'] = $this->getErrorMessages();
        } else {
            $data['errors'] = [];
        }

        if (session()->exists("form.{$this->formHandle}.success")) {
            $data['success'] = true;
        }

        $data['fields'] = Form::find($this->formHandle)->fields()->map(function ($field) use ($data) {
            return array_merge($field->toArray(), [
                'error' => array_get($data, "error.{$field->handle()}")
            ]);
        })->all();

        $this->addToDebugBar($data);

        $params = ['form' => $this->formHandle];

        if ($redirect = $this->get('redirect')) {
            $params['redirect'] = $redirect;
        }

        if ($error_redirect = $this->get('error_redirect')) {
            $params['error_redirect'] = $error_redirect;
        }

        $html .= '<input type="hidden" name="_params" value="'. Crypt::encrypt($params) .'" />';

        $html .= $this->parse($data);

        $html .= '</form>';

        return $html;
    }

    /**
     * Maps to {{ form:errors }}
     *
     * @return string
     */
    public function errors()
    {
        if (! $formset = $this->getForm()) {
            return false;
        }

        if (! $this->hasErrors()) {
            return false;
        }

        $errors = [];

        foreach (session('errors')->getBag('form.'.$formset)->all() as $error) {
            $errors[]['value'] = $error;
        }

        return ($this->content === '')    // If this is a single tag...
            ? !empty($errors)             // just output a boolean.
            : $this->parseLoop($errors);  // Otherwise, parse the content loop.
    }

    /**
     * Maps to {{ form:success }}
     *
     * @return bool
     */
    public function success()
    {
        if (! $formset = $this->getForm()) {
            return false;
        }

        return session()->has("form.{$formset}.success");
    }

    /**
     * Maps to {{ form:submission }}
     *
     * @return array
     */
    public function submission()
    {
        if ($this->success()) {
            return session('submission')->toArray();
        }
    }

    /**
     * Maps to {{ form:submissions }}
     *
     * @return array
     */
    public function submissions()
    {
        $submissions = Form::find($this->getForm())->submissions();

        $this->collection = collect_content($submissions);

        $this->filter();

        return $this->output();
    }

    /**
     * Get the sort order for a collection
     *
     * @return string
     */
    protected function getSortOrder()
    {
        return $this->get('sort', 'date');
    }

    /**
     * Get the formset specified either by the parameter or from within the context
     *
     * @return string
     */
    private function getForm()
    {
        if (! $form = $this->get(['form', 'in'], array_get($this->context, 'form'))) {
            throw new \Exception('A form handle is required on Form tags. Please refer to the docs for more information.');
        }

        if (! Form::find($form)) {
            throw new \Exception("Form with handle [$form] cannot be found.");
        }

        return $form;
    }

    /**
     * Does this form have errors?
     *
     * @return bool
     */
    private function hasErrors()
    {
        if (! $formset = $this->getForm()) {
            return false;
        }

        return (session()->has('errors'))
               ? session()->get('errors')->hasBag('form.'.$formset)
               : false;
    }

    /**
     * Get the errorBag from session
     *
     * @return object
     */
    private function getErrorBag()
    {
        if ($this->hasErrors()) {
            return session('errors')->getBag('form.'.$this->formHandle);
        }
    }

    /**
     * Get an array of all the error messages, keyed by their input names
     *
     * @return array
     */
    private function getErrors()
    {
        return array_combine($this->errorBag->keys(), $this->getErrorMessages());
    }

    /**
     * Get an array of all the error messages
     *
     * @return array
     */
    private function getErrorMessages()
    {
        return $this->errorBag->all();
    }

    /**
     * Add data to the debug bar
     *
     * Each form on the page will have its data placed in an array named
     * by its name. We'll use blink to keep track of the data as
     * we go and just update the collector.
     *
     * @param array $data
     */
    private function addToDebugBar($data)
    {
        if (! function_exists('debug_bar')) {
            return;
        }

        $debug = [];
        $debug[$this->formHandle] = $data;

        if ($this->blink->exists('debug_bar_data')) {
            $debug = array_merge($debug, $this->blink->get('debug_bar_data'));
        }

        $this->blink->put('debug_bar_data', $debug);

        try {
            debugbar()->getCollector('Forms')->setData($debug);
        } catch (DebugBarException $e) {
            // Collector doesn't exist yet. We'll create it.
            $collector = debugbar()->addCollector(new ConfigCollector($debug, 'Forms'));
        }
    }

    public function eventUrl($url, $relative = true)
    {
        return URL::prependSiteUrl(
            config('statamic.routes.action') . '/form/' . $url
        );
    }
}
