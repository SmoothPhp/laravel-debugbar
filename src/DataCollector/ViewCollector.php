<?php

namespace Barryvdh\Debugbar\DataCollector;

use DebugBar\Bridge\Twig\TwigCollector;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\DataCollector\Util\ValueExporter;

class ViewCollector extends TwigCollector
{
    protected $templates = array();

    protected $collect_data;

    /**
     * Create a ViewCollector
     *
     * @param bool $collectData Collects view data when tru
     */
    public function __construct($collectData = true)
    {
        $this->collect_data = $collectData;
        $this->name = 'views';
        $this->templates = array();
        $this->exporter = new ValueExporter();
    }

    public function getName()
    {
        return 'views';
    }

    public function getWidgets()
    {
        return array(
            'views'       => array(
                'icon'    => 'leaf',
                'widget'  => 'PhpDebugBar.Widgets.TemplatesWidget',
                'map'     => 'views',
                'default' => '[]'
            ),
            'views:badge' => array(
                'map'     => 'views.nb_templates',
                'default' => 0
            )
        );
    }

    /**
     * Add a View instance to the Collector
     *
     * @param \Illuminate\View\View $view
     */
    public function addView(View $view)
    {
        $name = $view->getName();
        $path = $view->getPath();
        if ($path) {
            $path = ltrim(str_replace(base_path(), '', realpath($path)), '/');
        }

        if (substr($path, -10) == '.blade.php') {
            $type = 'blade';
        } else {
            $type = pathinfo($path, PATHINFO_EXTENSION);
        }

        if (!$this->collect_data) {
            $params = array_keys($view->getData());
        } else {
            $data = array();
            foreach ($view->getData() as $key => $value) {
                if (in_array($key, ['__env', 'app', 'errors', 'obLevel', 'currentUser'])) {
                    continue;
                }
                $data[$key] = $this->exportValue($value);
            }
            $params = $data;
        }

        $this->templates[] = array(
            'name'        => $path ? sprintf('%s (%s)', $name, $path) : $name,
            'param_count' => count($params),
            'params'      => $params,
            'type'        => $type,
        );
    }

    public function collect()
    {
        $templates = $this->templates;

        return array(
            'nb_templates' => count($templates),
            'templates'    => $templates,
        );
    }

    /**
     * Converts a PHP value to a string.
     *
     * @param mixed $value The PHP value
     * @param int   $depth only for internal usage
     * @param bool  $deep  only for internal usage
     *
     * @return string The string representation of the given value
     */
    public function exportValue($value, $depth = 1, $deep = false)
    {
        if (is_object($value)) {
            if ($value instanceof \DateTime || $value instanceof \DateTimeInterface) {
                return sprintf('Object(%s) - %s', get_class($value), $value->format(\DateTime::ISO8601));
            }
            if($value instanceof \stdClass)
            {
                return $this->exportValue(json_decode(json_encode($value),true),$depth + 1, true);
            }
            return sprintf('Object(%s)', get_class($value));
        }

        if (is_array($value)) {
            if (empty($value)) {
                return '[]';
            }

            $indent = str_repeat('  ', $depth);

            $a = array();
            foreach ($value as $k => $v) {
                if (is_array($v)) {
                    $deep = true;
                }
                $a[] = sprintf('%s => %s', $k, $this->exportValue($v, $depth + 1, $deep));
            }

            if ($deep) {
                return sprintf("[\n%s%s\n%s]", $indent, implode(sprintf(", \n%s", $indent), $a), str_repeat('  ', $depth - 1));
            }

            return sprintf('[%s]', implode(', ', $a));
        }

        if (is_resource($value)) {
            return sprintf('Resource(%s#%d)', get_resource_type($value), $value);
        }

        if (null === $value) {
            return 'null';
        }

        if (false === $value) {
            return 'false';
        }

        if (true === $value) {
            return 'true';
        }


        return (string) $value;
    }

}
